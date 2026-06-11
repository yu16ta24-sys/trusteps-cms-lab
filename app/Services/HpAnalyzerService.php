<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Domain;
use App\Models\HpFact;
use App\Models\HpSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HpAnalyzerService
{
    public const EXTRACTOR_VERSION = 'hp_analyzer_v1';

    // ポータルドメインのパターン
    private const PORTAL_PATTERNS = [
        'tabelog'    => ['tabelog.com'],
        'hotpepper'  => ['hotpepper.jp', 'beauty.hotpepper.jp'],
        'jalan'      => ['jalan.net', 'travel.rakuten.co.jp', 'rurubu.travel'],
        'suumo'      => ['suumo.jp'],
        'other'      => [
            'homes.co.jp', 'athome.co.jp', 'chintai.net',
            'goo-net.com', 'carsensor.net',
            'epark.jp', 'reserva.be',
            'indeed.com', 'hellowork.mhlw.go.jp',
        ],
    ];

    // CMSの判定パターン
    private const CMS_PATTERNS = [
        'wordpress' => [
            '/wp-content/', '/wp-includes/', 'wp-login.php',
            'name="generator" content="WordPress',
        ],
        'wix'       => ['wix.com', 'wixsite.com', 'X-Wix-'],
        'studio'    => ['studio.site', 'studiodesign'],
        'jimdo'     => ['jimdo.com', 'jimdofree.com'],
        'squarespace' => ['squarespace.com', 'squarespace-cdn'],
        'weebly'    => ['weebly.com'],
        'shopify'   => ['myshopify.com', 'cdn.shopify.com'],
        'base'      => ['thebase.in', 'base.ec'],
        'stores'    => ['stores.jp'],
    ];

    // お知らせ・ニュース系のパターン
    private const NEWS_PATTERNS = [
        'お知らせ', 'ニュース', 'news', 'topics', 'information',
        'トピックス', 'インフォメーション', 'what\'s new', 'whats new',
        'ブログ', 'blog', '新着情報', '最新情報',
    ];

    /**
     * HPを解析してスナップショット・ファクトを保存する
     *
     * @return array{success:bool, message:string, snapshot_id:int|null, fact:array|null}
     */
    public function analyze(Company $company): array
    {
        $domain = $company->primaryDomain;

        if (!$domain || !$domain->url) {
            return [
                'success'     => false,
                'message'     => 'primary_domainが未設定のため解析できません。',
                'snapshot_id' => null,
                'fact'        => null,
            ];
        }

        $url = $domain->url;

        // HTTP取得
        $fetchResult = $this->fetchHtml($url);

        // スナップショット保存
        $snapshot = DB::transaction(function () use ($domain, $url, $fetchResult) {
            return HpSnapshot::create([
                'domain_id'    => $domain->id,
                'crawl_type'   => 'manual_verify',
                'requested_url' => $url,
                'final_url'    => $fetchResult['final_url'],
                'http_status'  => $fetchResult['http_status'],
                'error_type'   => $fetchResult['error_type'],
                'error_message' => $fetchResult['error_message'],
                'crawled_at'   => now(),
            ]);
        });

        // HTTP取得失敗の場合はここで終了
        if (!$fetchResult['success']) {
            return [
                'success'     => false,
                'message'     => 'HP取得失敗：' . ($fetchResult['error_message'] ?? 'Unknown error'),
                'snapshot_id' => $snapshot->id,
                'fact'        => null,
            ];
        }

        $html = $fetchResult['html'];
        $headers = $fetchResult['headers'];

        // HTML解析
        $factData = $this->analyzeHtml($html, $headers, $url, $fetchResult['final_url']);
        $factData['hp_snapshot_id'] = $snapshot->id;
        $factData['extractor_version'] = self::EXTRACTOR_VERSION;
        $factData['extracted_at'] = now();

        // ファクト保存
        $fact = DB::transaction(function () use ($snapshot, $factData) {
            // 既存ファクトがあれば削除して新規作成
            HpFact::where('hp_snapshot_id', $snapshot->id)->delete();
            return HpFact::create($factData);
        });

        return [
            'success'     => true,
            'message'     => 'HP解析完了',
            'snapshot_id' => $snapshot->id,
            'fact'        => $factData,
        ];
    }

    /**
     * URLからHTMLを取得する
     *
     * @return array{success:bool, html:string|null, headers:array, final_url:string, http_status:int|null, error_type:string|null, error_message:string|null}
     */
    private function fetchHtml(string $url): array
    {
        $url = $this->normalizeUrl($url);

        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => implode("\r\n", [
                    'User-Agent: Mozilla/5.0 (compatible; TRUSTEPSBot/1.0)',
                    'Accept: text/html,application/xhtml+xml',
                    'Accept-Language: ja,en;q=0.5',
                ]),
                'timeout'         => 15,
                'follow_location' => 1,
                'max_redirects'   => 5,
                'ignore_errors'   => true,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        try {
            $html = @file_get_contents($url, false, $context);

            if ($html === false) {
                return [
                    'success'       => false,
                    'html'          => null,
                    'headers'       => [],
                    'final_url'     => $url,
                    'http_status'   => null,
                    'error_type'    => 'timeout',
                    'error_message' => 'ページの取得に失敗しました。',
                ];
            }

            // レスポンスヘッダーを解析
            $headers = [];
            $httpStatus = null;
            $finalUrl = $url;

            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (preg_match('#^HTTP/\S+\s+(\d+)#i', $header, $m)) {
                        $httpStatus = (int) $m[1];
                    } elseif (strpos($header, ':') !== false) {
                        [$key, $val] = explode(':', $header, 2);
                        $headers[strtolower(trim($key))] = trim($val);
                    }
                }
            }

            // 文字コード変換（Shift-JIS等への対応）
            $encoding = mb_detect_encoding($html, ['UTF-8', 'SJIS', 'EUC-JP', 'ISO-2022-JP'], true);
            if ($encoding && $encoding !== 'UTF-8') {
                $html = mb_convert_encoding($html, 'UTF-8', $encoding);
            }

            return [
                'success'       => true,
                'html'          => $html,
                'headers'       => $headers,
                'final_url'     => $finalUrl,
                'http_status'   => $httpStatus ?? 200,
                'error_type'    => null,
                'error_message' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('HpAnalyzerService: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return [
                'success'       => false,
                'html'          => null,
                'headers'       => [],
                'final_url'     => $url,
                'http_status'   => null,
                'error_type'    => 'unknown',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * HTMLを解析してfactデータを生成する
     */
    private function analyzeHtml(string $html, array $headers, string $requestedUrl, string $finalUrl): array
    {
        $htmlLower = strtolower($html);

        // ====== A: 基本情報 ======
        $title = $this->extractTitle($html);
        $description = $this->extractMetaDescription($html);
        $lastModified = $headers['last-modified'] ?? null;

        // ====== B: 技術スタック ======
        $sslEnabled = str_starts_with($finalUrl, 'https://') || str_starts_with($requestedUrl, 'https://');
        $hasViewport = str_contains($htmlLower, 'name="viewport"') || str_contains($htmlLower, "name='viewport'");
        [$cmsType, $builderType] = $this->detectCms($html, $htmlLower);

        // ====== C: 更新状況 ======
        $hasNews = $this->detectNews($htmlLower);
        $latestPostDate = $this->extractLatestDate($html);
        $stalenessDays = $this->calcStalenessDays($latestPostDate);
        $updateStatus = $this->calcUpdateStatus($stalenessDays);
        $footerYearStatus = $this->detectFooterYear($html);

        // ====== D: コンテンツ品質 ======
        $pageCount = $this->countInternalLinks($html, $finalUrl);
        $hasContactForm = str_contains($htmlLower, '<form') && (
            str_contains($htmlLower, 'contact') ||
            str_contains($htmlLower, 'お問い合わせ') ||
            str_contains($htmlLower, 'inquiry') ||
            str_contains($htmlLower, 'メール')
        );
        $hasPublicEmail = (bool) preg_match('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $html);
        $hasPhone = (bool) preg_match('/0\d{1,4}[-\s]?\d{1,4}[-\s]?\d{3,4}/', $html);
        $hasMap = str_contains($htmlLower, 'google.com/maps') ||
                  str_contains($htmlLower, 'maps.google') ||
                  str_contains($htmlLower, 'goo.gl/maps') ||
                  str_contains($htmlLower, '<iframe') && str_contains($htmlLower, 'maps');
        $imageCount = substr_count($htmlLower, '<img');
        $wordCount = $this->calcWordCount($html);

        // ====== E: ポータル依存 ======
        $portalResult = $this->detectPortals($html, $htmlLower);
        $hasPortalLink = !empty($portalResult['links']);
        $portalDependencyLevel = $this->calcPortalDependencyLevel($portalResult);

        // SNS検出
        $hasSnsLink = str_contains($htmlLower, 'instagram.com') ||
                      str_contains($htmlLower, 'twitter.com') ||
                      str_contains($htmlLower, 'x.com/') ||
                      str_contains($htmlLower, 'facebook.com') ||
                      str_contains($htmlLower, 'youtube.com') ||
                      str_contains($htmlLower, 'tiktok.com') ||
                      str_contains($htmlLower, 'line.me');

        $hasGoogleBusinessLink = str_contains($htmlLower, 'maps.app.goo.gl') ||
                                  str_contains($htmlLower, 'g.page') ||
                                  (str_contains($htmlLower, 'google.com/maps') && str_contains($htmlLower, 'place'));

        // contact_method_type
        $contactMethodType = $this->calcContactMethodType($hasContactForm, $hasPublicEmail, $hasPhone);

        // ====== HP改善余地スコア計算 ======
        $improvementScore = $this->calcImprovementScore([
            'ssl_enabled'       => $sslEnabled,
            'has_viewport'      => $hasViewport,
            'cms_type'          => $cmsType,
            'staleness_days'    => $stalenessDays,
            'has_news'          => $hasNews,
            'has_contact_form'  => $hasContactForm,
            'portal_level'      => $portalDependencyLevel,
        ]);

        return [
            // 既存カラム
            'has_ec'                    => false,
            'has_reservation'           => str_contains($htmlLower, '予約') && (str_contains($htmlLower, 'form') || str_contains($htmlLower, 'reserve')),
            'has_recruiting'            => str_contains($htmlLower, '採用') || str_contains($htmlLower, '求人') || str_contains($htmlLower, 'recruit'),
            'has_portal_link'           => $hasPortalLink,
            'has_sns_link'              => $hasSnsLink,
            'has_google_business_link'  => $hasGoogleBusinessLink,
            'has_contact_form'          => $hasContactForm,
            'has_public_email'          => $hasPublicEmail,
            'has_phone'                 => $hasPhone,
            'contact_method_type'       => $contactMethodType,
            'update_status'             => $updateStatus,
            'has_update_targets'        => $hasNews,
            'cms_type'                  => $cmsType,
            'builder_type'              => $builderType,
            'mobile_friendly'           => $hasViewport,
            'ssl_enabled'               => $sslEnabled,
            'footer_year_status'        => $footerYearStatus,
            'portal_dependency_level'   => $portalDependencyLevel,
            // 新規カラム
            'hp_title'                  => $title ? mb_substr($title, 0, 500) : null,
            'hp_description'            => $description ? mb_substr($description, 0, 1000) : null,
            'hp_last_modified'          => $lastModified ? mb_substr($lastModified, 0, 100) : null,
            'hp_has_news'               => $hasNews,
            'hp_latest_post_date'       => $latestPostDate,
            'hp_update_staleness_days'  => $stalenessDays,
            'hp_page_count'             => $pageCount,
            'hp_has_map'                => $hasMap,
            'hp_image_count'            => $imageCount,
            'hp_word_count'             => $wordCount,
            'hp_has_tabelog'            => $portalResult['tabelog'],
            'hp_has_hotpepper'          => $portalResult['hotpepper'],
            'hp_has_jalan'              => $portalResult['jalan'],
            'hp_has_suumo'              => $portalResult['suumo'],
            'hp_portal_links'           => !empty($portalResult['links']) ? json_encode($portalResult['links'], JSON_UNESCAPED_UNICODE) : null,
            'hp_improvement_score'      => $improvementScore,
        ];
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return trim(strip_tags($m[1]));
        }
        return null;
    }

    private function extractMetaDescription(string $html): ?string
    {
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\'][^>]*>/i', $html, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function detectCms(string $html, string $htmlLower): array
    {
        $cmsType = 'unknown';
        $builderType = 'none';

        foreach (self::CMS_PATTERNS as $cms => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($htmlLower, strtolower($pattern))) {
                    if (in_array($cms, ['wordpress'])) {
                        $cmsType = $cms;
                    } else {
                        $builderType = $cms;
                        $cmsType = 'builder';
                    }
                    break 2;
                }
            }
        }

        return [$cmsType, $builderType];
    }

    private function detectNews(string $htmlLower): bool
    {
        foreach (self::NEWS_PATTERNS as $pattern) {
            if (str_contains($htmlLower, strtolower($pattern))) {
                return true;
            }
        }
        return false;
    }

    private function extractLatestDate(string $html): ?string
    {
        // ISO日付 (2023-01-15)
        if (preg_match_all('/20\d{2}[-\/年]\d{1,2}[-\/月]\d{1,2}/', $html, $matches)) {
            $dates = array_unique($matches[0]);
            rsort($dates);
            return $dates[0] ?? null;
        }
        return null;
    }

    private function calcStalenessDays(?string $dateStr): ?int
    {
        if (!$dateStr) {
            return null;
        }

        try {
            $normalized = preg_replace('/[年月]/', '-', $dateStr);
            $normalized = rtrim($normalized, '日');
            $date = new \DateTime($normalized);
            $now = new \DateTime();
            return (int) $date->diff($now)->days;
        } catch (\Throwable) {
            return null;
        }
    }

    private function calcUpdateStatus(?int $stalenessDays): string
    {
        if ($stalenessDays === null) {
            return 'unknown';
        }
        if ($stalenessDays <= 90) {
            return 'active';
        }
        if ($stalenessDays <= 365) {
            return 'partial_active';
        }
        if ($stalenessDays <= 730) {
            return 'stale_1y';
        }
        return 'stale_2y';
    }

    private function detectFooterYear(string $html): string
    {
        if (preg_match('/copyright[^<]*20(\d{2})/i', $html, $m) ||
            preg_match('/©[^<]*20(\d{2})/i', $html, $m) ||
            preg_match('/\&copy;[^<]*20(\d{2})/i', $html, $m)) {
            $year = (int) ('20' . $m[1]);
            $currentYear = (int) date('Y');
            $diff = $currentYear - $year;
            if ($diff <= 1) {
                return 'current';
            }
            if ($diff <= 2) {
                return 'old_1_2y';
            }
            return 'old_3y_over';
        }
        return 'unknown';
    }

    private function countInternalLinks(string $html, string $baseUrl): int
    {
        $host = parse_url($baseUrl, PHP_URL_HOST) ?? '';
        $count = 0;

        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                if (str_starts_with($href, '/') ||
                    str_contains($href, $host)) {
                    $count++;
                }
            }
        }
        return min($count, 999);
    }

    private function calcWordCount(string $html): int
    {
        // タグ・スクリプト・スタイルを除去してテキスト量を計算
        $text = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $text);
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return mb_strlen($text);
    }

    private function detectPortals(string $html, string $htmlLower): array
    {
        $result = [
            'tabelog'   => false,
            'hotpepper' => false,
            'jalan'     => false,
            'suumo'     => false,
            'links'     => [],
        ];

        foreach (self::PORTAL_PATTERNS as $type => $domains) {
            foreach ($domains as $domain) {
                if (str_contains($htmlLower, strtolower($domain))) {
                    if (array_key_exists($type, $result)) {
                        $result[$type] = true;
                    }
                    $result['links'][] = $domain;
                    break;
                }
            }
        }

        $result['links'] = array_unique($result['links']);
        return $result;
    }

    private function calcPortalDependencyLevel(array $portalResult): string
    {
        $count = array_sum([
            (int) $portalResult['tabelog'],
            (int) $portalResult['hotpepper'],
            (int) $portalResult['jalan'],
            (int) $portalResult['suumo'],
        ]);

        if ($count === 0) {
            return 'none';
        }
        if ($count === 1) {
            return 'low';
        }
        if ($count === 2) {
            return 'medium';
        }
        return 'high';
    }

    private function calcContactMethodType(bool $hasForm, bool $hasEmail, bool $hasPhone): string
    {
        $methods = [];
        if ($hasEmail) {
            $methods[] = 'email';
        }
        if ($hasForm) {
            $methods[] = 'form';
        }
        if ($hasPhone) {
            $methods[] = 'phone';
        }

        if (empty($methods)) {
            return 'none';
        }
        return implode('_', $methods);
    }

    /**
     * HP改善余地スコア（0〜5点）を計算する
     */
    private function calcImprovementScore(array $data): int
    {
        $score = 0;

        // SSL未対応 +2
        if (!$data['ssl_enabled']) {
            $score += 2;
        }

        // スマホ非対応 +2
        if (!$data['has_viewport']) {
            $score += 2;
        }

        // CMS未使用（静的HTML） +1
        if ($data['cms_type'] === 'unknown') {
            $score += 1;
        }

        // 更新が1年以上古い +2
        if ($data['staleness_days'] !== null && $data['staleness_days'] > 365) {
            $score += 2;
        }

        // お知らせなし +1
        if (!$data['has_news']) {
            $score += 1;
        }

        // 問い合わせなし +1
        if (!$data['has_contact_form']) {
            $score += 1;
        }

        // ポータル依存が高い -1
        if (in_array($data['portal_level'], ['medium', 'high'])) {
            $score -= 1;
        }

        // 0〜9点を0〜5点に正規化
        $score = max(0, $score);
        return (int) round($score / 9 * 5);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }
}
