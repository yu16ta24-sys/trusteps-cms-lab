<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Domain;
use App\Models\HpFact;
use App\Models\HpSnapshot;
use App\Models\IndustryScoreObservation;
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

    // 2階層クロール（Phase 2-B）で追加取得するサブページの探索キーワード（優先度順）
    private const SUBPAGE_KEYWORDS = [
        'contact', 'inquiry', 'お問い合わせ', '問い合わせ',
        'about', 'company', '会社概要', '会社情報', '企業情報',
        'recruit', '採用', '求人',
        'works', 'case', 'portfolio', '施工事例', '実績', '事例',
        'service', 'services', 'サービス',
        'voice', 'review', 'testimonial', 'お客様の声',
        'faq', 'よくある質問',
        'price', 'pricing', '料金', '費用',
        'staff', 'team', 'member', 'スタッフ',
    ];

    // サブページ取得の上限件数
    private const MAX_SUBPAGES = 5;

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

        // JSレンダリング判定
        if ($this->detectJsRendering($html)) {
            $factData = [
                'hp_snapshot_id'         => $snapshot->id,
                'extractor_version'      => self::EXTRACTOR_VERSION,
                'extracted_at'           => now(),
                'hp_js_rendering_required' => true,
            ];
            DB::transaction(function () use ($snapshot, $factData) {
                HpFact::where('hp_snapshot_id', $snapshot->id)->delete();
                HpFact::create($factData);
            });
            return [
                'success'              => true,
                'js_rendering_required' => true,
                'message'              => 'JSレンダリングが必要なサイトのため自動解析できませんでした。',
                'snapshot_id'          => $snapshot->id,
                'fact'                 => $factData,
                'url_upgraded'         => $fetchResult['url_upgraded'] ?? false,
                'https_url'            => $fetchResult['https_url'] ?? null,
                'https_accessible'     => $fetchResult['https_accessible'] ?? false,
            ];
        }

        // ====== Phase 2-B: 2階層クロール（サブページ取得） ======
        $topHtml      = $html;
        $baseUrl      = $fetchResult['final_url'] ?: $url;
        $crawledUrls  = [];
        $skippedUrls  = [];
        $subpageHtmls = [];

        $subpageUrls = $this->extractSubpageUrls($topHtml, $baseUrl, self::MAX_SUBPAGES);
        foreach ($subpageUrls as $subUrl) {
            if (count($crawledUrls) >= self::MAX_SUBPAGES) {
                break;
            }
            try {
                // サブページはタイムアウト短め(10s)・SSL再判定なし
                $subFetch = $this->fetchHtml($subUrl, 10, false);
                if (!$subFetch['success'] || empty($subFetch['html'])) {
                    $skippedUrls[] = $subUrl;
                    continue;
                }
                // JSレンダリングサイトはスキップ
                if ($this->detectJsRendering($subFetch['html'])) {
                    $skippedUrls[] = $subUrl;
                    continue;
                }
                $subpageHtmls[] = $subFetch['html'];
                $crawledUrls[]  = $subUrl;
            } catch (\Throwable $e) {
                // 取得失敗は握りつぶしてスキップ
                $skippedUrls[] = $subUrl;
            }
        }

        // トップ＋サブページのHTMLをマージ（既存解析はそのまま、入力HTMLが増えるだけ）
        $combinedHtml = $topHtml;
        if (!empty($subpageHtmls)) {
            $combinedHtml = $topHtml . "\n" . implode("\n", $subpageHtmls);
        }

        // HTML解析（地域キーワード検出のため municipality を読み込んで渡す）
        $company->loadMissing('municipality.prefecture');
        $factData = $this->analyzeHtml($combinedHtml, $headers, $url, $baseUrl, $fetchResult['ssl_enabled'] ?? null, $company);
        $factData['hp_snapshot_id'] = $snapshot->id;
        $factData['extractor_version'] = self::EXTRACTOR_VERSION;
        $factData['extracted_at'] = now();
        $factData['hp_crawled_subpages'] = !empty($crawledUrls) ? json_encode($crawledUrls, JSON_UNESCAPED_UNICODE) : null;
        $factData['hp_subpage_count'] = count($crawledUrls);

        // ファクト保存
        $fact = DB::transaction(function () use ($snapshot, $factData) {
            // 既存ファクトがあれば削除して新規作成
            HpFact::where('hp_snapshot_id', $snapshot->id)->delete();
            return HpFact::create($factData);
        });

        // 業種別観測値を記録
        $this->recordObservations($company, $factData);

        return [
            'success'          => true,
            'message'          => 'HP解析完了',
            'snapshot_id'      => $snapshot->id,
            'fact'             => $factData,
            'url_upgraded'     => $fetchResult['url_upgraded'] ?? false,
            'https_url'        => $fetchResult['https_url'] ?? null,
            'https_accessible' => $fetchResult['https_accessible'] ?? false,
            'crawled_subpages' => $crawledUrls,
            'skipped_subpages' => $skippedUrls,
        ];
    }

    /**
     * トップページHTMLから、追加取得すべきサブページの絶対URLを抽出する。
     * キーワード優先度順に最大 $maxCount 件を返す。
     *
     * @return string[]
     */
    private function extractSubpageUrls(string $topHtml, string $topUrl, int $maxCount = 5): array
    {
        $host = parse_url($this->normalizeUrl($topUrl), PHP_URL_HOST) ?? '';
        if ($host === '') {
            return [];
        }

        $topNorm = $this->normalizeUrlForCompare($topUrl);

        // 候補URLを収集（同一ドメイン・ファイル除外・アンカー除外・トップ自身除外）
        // key: 比較用に正規化したURL, value: ['url'=>絶対URL, 'haystack'=>キーワード照合用文字列]
        $candidates = [];
        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $topHtml, $matches)) {
            foreach ($matches[1] as $hrefRaw) {
                $href = trim($hrefRaw);
                if ($href === '' || str_starts_with($href, '#')) {
                    continue;
                }
                $lowPrefix = strtolower($href);
                if (str_starts_with($lowPrefix, 'mailto:') ||
                    str_starts_with($lowPrefix, 'tel:') ||
                    str_starts_with($lowPrefix, 'javascript:')) {
                    continue;
                }

                $abs = $this->resolveUrl($href, $topUrl);
                $abs = preg_replace('/#.*$/', '', $abs); // フラグメント除去
                if (!is_string($abs) || $abs === '') {
                    continue;
                }

                // 同一ドメインのみ
                $absHost = parse_url($abs, PHP_URL_HOST) ?? '';
                if ($absHost === '' || strcasecmp($absHost, $host) !== 0) {
                    continue;
                }

                // 画像・PDF等のファイルは除外
                $path = parse_url($abs, PHP_URL_PATH) ?? '';
                if (preg_match('/\.(jpe?g|png|gif|svg|webp|bmp|ico|pdf|zip|rar|docx?|xlsx?|pptx?|mp4|mov|avi|mp3|css|js)$/i', $path)) {
                    continue;
                }

                $absNorm = $this->normalizeUrlForCompare($abs);

                // トップページ自身は除外（取得済み）
                if ($absNorm === $topNorm) {
                    continue;
                }

                if (!isset($candidates[$absNorm])) {
                    $candidates[$absNorm] = [
                        'url'      => $abs,
                        'haystack' => strtolower(urldecode($href) . ' ' . $abs),
                    ];
                }
            }
        }

        if (empty($candidates)) {
            return [];
        }

        // キーワード優先度順に選定
        $selected = [];   // 比較用正規化URL => 絶対URL
        foreach (self::SUBPAGE_KEYWORDS as $kw) {
            if (count($selected) >= $maxCount) {
                break;
            }
            $kwLow = strtolower($kw);
            foreach ($candidates as $norm => $info) {
                if (count($selected) >= $maxCount) {
                    break;
                }
                if (isset($selected[$norm])) {
                    continue;
                }
                if (str_contains($info['haystack'], $kwLow)) {
                    $selected[$norm] = $info['url'];
                }
            }
        }

        return array_values($selected);
    }

    /**
     * URL比較用に正規化する（フラグメント除去・末尾スラッシュ除去・小文字化）。
     */
    private function normalizeUrlForCompare(string $url): string
    {
        $url = $this->normalizeUrl($url);
        $url = preg_replace('/#.*$/', '', $url);
        return strtolower(rtrim($url, '/'));
    }

    /**
     * URLからHTMLを取得する
     *
     * @return array{success:bool, html:string|null, headers:array, final_url:string, http_status:int|null, error_type:string|null, error_message:string|null}
     */
    private function fetchHtml(string $url, int $timeout = 15, bool $detectSsl = true): array
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
                'timeout'         => $timeout,
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

            // SSL（HTTPS）対応の実態判定。ここに到達した時点で正規化後URLの取得は成功している。
            // サブページ取得時($detectSsl=false)は追加プローブを行わずスキームのみで簡易判定する。
            if ($detectSsl) {
                $normalizedIsHttps = str_starts_with(strtolower($url), 'https://');
                $sslInfo           = $this->detectSslEnabled($url, $normalizedIsHttps);
            } else {
                $isHttps = str_starts_with(strtolower($url), 'https://');
                $sslInfo = [
                    'ssl_enabled'      => $isHttps,
                    'https_accessible' => $isHttps,
                    'url_upgraded'     => false,
                    'https_url'        => preg_replace('#^https?://#i', 'https://', $url),
                ];
            }

            return [
                'success'          => true,
                'html'             => $html,
                'headers'          => $headers,
                'final_url'        => $finalUrl,
                'http_status'      => $httpStatus ?? 200,
                'ssl_enabled'      => $sslInfo['ssl_enabled'],
                'https_accessible' => $sslInfo['https_accessible'],
                'url_upgraded'     => $sslInfo['url_upgraded'],
                'https_url'        => $sslInfo['https_url'],
                'error_type'       => null,
                'error_message'    => null,
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
     * SSL（HTTPS）対応状況を実態ベースで判定する。
     * https:// で実際に取得できるかを試行し、その成否を ssl_enabled とする。
     *
     * @return array{ssl_enabled:bool, https_accessible:bool, url_upgraded:bool, https_url:string}
     */
    private function detectSslEnabled(string $requestedUrl, bool $mainHttpsSucceeded = false): array
    {
        $reqIsHttp = str_starts_with(strtolower($requestedUrl), 'http://');
        $httpsUrl  = preg_replace('#^https?://#i', 'https://', $requestedUrl);

        // 1. 本フェッチが https で成功済みなら再試行不要
        $httpsAccessible = $mainHttpsSucceeded;

        // 2. 未確認なら https:// で実アクセスを試行（証明書あり・到達可能か）
        if (!$httpsAccessible) {
            try {
                $httpsContext = stream_context_create([
                    'http' => [
                        'method'          => 'GET',
                        'header'          => 'User-Agent: Mozilla/5.0 (compatible; TRUSTEPSBot/1.0)',
                        'timeout'         => 5,
                        'follow_location' => 1,
                        'max_redirects'   => 5,
                        'ignore_errors'   => true,
                    ],
                    'ssl' => [
                        'verify_peer'      => false,
                        'verify_peer_name' => false,
                    ],
                ]);

                $body = @file_get_contents($httpsUrl, false, $httpsContext);
                if ($body !== false) {
                    $status = null;
                    if (isset($http_response_header) && is_array($http_response_header)) {
                        foreach ($http_response_header as $h) {
                            if (preg_match('#^HTTP/\S+\s+(\d+)#i', $h, $m)) {
                                $status = (int) $m[1];
                            }
                        }
                    }
                    // ステータス不明 or 2xx/3xx を到達成功とみなす
                    $httpsAccessible = ($status === null) || ($status >= 200 && $status < 400);
                }
            } catch (\Throwable $e) {
                $httpsAccessible = false;
            }
        }

        return [
            'ssl_enabled'      => $httpsAccessible,
            'https_accessible' => $httpsAccessible,
            // 元URLが http:// かつ https 到達成功 → https へ昇格可能
            'url_upgraded'     => $reqIsHttp && $httpsAccessible,
            'https_url'        => $httpsUrl,
        ];
    }

    /**
     * HTMLを解析してfactデータを生成する
     */
    private function analyzeHtml(string $html, array $headers, string $requestedUrl, string $finalUrl, ?bool $sslEnabled = null, ?Company $company = null): array
    {
        $htmlLower = strtolower($html);

        // ====== A: 基本情報 ======
        $title = $this->extractTitle($html);
        $description = $this->extractMetaDescription($html);
        $lastModified = $headers['last-modified'] ?? null;

        // ====== B: 技術スタック ======
        // fetchHtml() で実態判定済みの値があればそれを採用、なければ従来のスキームチェックにフォールバック
        $sslEnabled = $sslEnabled ?? (str_starts_with($finalUrl, 'https://') || str_starts_with($requestedUrl, 'https://'));
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
        $contactEmail   = $this->extractContactEmail($html);
        $contactFormUrl = $this->extractContactFormUrl($html, $htmlLower, $hasContactForm, $finalUrl);
        $contactPhone   = $this->extractContactPhone($html);
        $hasPublicEmail = $contactEmail !== null;
        $hasPhone       = $contactPhone !== null;
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

        // ====== F: コンテンツ検出（Phase 2-A） ======
        // 施工事例・実績
        $hasCaseStudies = (
            str_contains($htmlLower, '施工事例') ||
            str_contains($htmlLower, '施工実績') ||
            str_contains($htmlLower, '工事実績') ||
            str_contains($htmlLower, '導入事例') ||
            str_contains($htmlLower, '実績紹介') ||
            str_contains($htmlLower, 'works') ||
            str_contains($htmlLower, 'case')
        );

        // 料金・価格
        $hasPricing = (
            str_contains($htmlLower, '料金') ||
            str_contains($htmlLower, '価格') ||
            str_contains($htmlLower, '費用') ||
            str_contains($htmlLower, '見積') ||
            str_contains($htmlLower, 'price') ||
            str_contains($htmlLower, '円〜') ||
            (bool) preg_match('/[0-9,]+円/', $html)
        );

        // お客様の声・レビュー
        $hasTestimonials = (
            str_contains($htmlLower, 'お客様の声') ||
            str_contains($htmlLower, 'お客さまの声') ||
            str_contains($htmlLower, '口コミ') ||
            str_contains($htmlLower, 'レビュー') ||
            str_contains($htmlLower, '評判') ||
            str_contains($htmlLower, 'voice') ||
            str_contains($htmlLower, 'testimonial')
        );

        // FAQ
        $hasFaq = (
            str_contains($htmlLower, 'よくある質問') ||
            str_contains($htmlLower, 'faq') ||
            str_contains($htmlLower, 'q&a') ||
            str_contains($htmlLower, 'よくあるご質問')
        );

        // スタッフ・代表者紹介
        $hasStaffIntro = (
            str_contains($htmlLower, 'スタッフ紹介') ||
            str_contains($htmlLower, 'スタッフ一覧') ||
            str_contains($htmlLower, '代表挨拶') ||
            str_contains($htmlLower, '代表メッセージ') ||
            str_contains($htmlLower, 'チーム紹介') ||
            str_contains($htmlLower, 'staff') ||
            str_contains($htmlLower, 'team') ||
            str_contains($htmlLower, 'about us')
        );

        // 採用ページ
        $hasRecruitPage = (
            str_contains($htmlLower, '採用情報') ||
            str_contains($htmlLower, '求人情報') ||
            str_contains($htmlLower, '採用募集') ||
            str_contains($htmlLower, '一緒に働く') ||
            str_contains($htmlLower, 'recruit') ||
            str_contains($htmlLower, '求人') ||
            str_contains($htmlLower, '募集要項')
        );

        // サービス説明の充実度
        $hasServiceDetail = (
            str_contains($htmlLower, 'サービス') ||
            str_contains($htmlLower, '事業内容') ||
            str_contains($htmlLower, 'service') ||
            str_contains($htmlLower, '特徴') ||
            str_contains($htmlLower, '強み') ||
            str_contains($htmlLower, '選ばれる理由')
        );

        // 会社概要
        $hasCompanyProfile = (
            str_contains($htmlLower, '会社概要') ||
            str_contains($htmlLower, '会社情報') ||
            str_contains($htmlLower, 'company') ||
            str_contains($htmlLower, '企業情報') ||
            str_contains($htmlLower, '運営会社')
        );

        // 代表者名（個人名パターン）
        $hasOwnerName = (bool) preg_match('/代表[取締役社長：: 　]*[\x{3040}-\x{9FFF}]{2,6}/u', $html);

        // 地域密着キーワード（会社の都道府県名・市区町村名がHP内に出現するか）
        $hasLocalKeyword = false;
        if ($company && $company->municipality) {
            $prefName = $company->municipality->prefecture->name ?? '';
            $cityName = $company->municipality->name ?? '';
            if ($prefName !== '' && str_contains($html, $prefName)) {
                $hasLocalKeyword = true;
            }
            if ($cityName !== '' && str_contains($html, $cityName)) {
                $hasLocalKeyword = true;
            }
        }

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
            'hp_contact_email'          => $contactEmail,
            'hp_contact_form_url'       => $contactFormUrl,
            'hp_contact_phone'          => $contactPhone,
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
            // コンテンツ検出（Phase 2-A）
            'hp_has_case_studies'       => $hasCaseStudies,
            'hp_has_pricing'            => $hasPricing,
            'hp_has_testimonials'       => $hasTestimonials,
            'hp_has_faq'                => $hasFaq,
            'hp_has_staff_intro'        => $hasStaffIntro,
            'hp_has_recruit_page'       => $hasRecruitPage,
            'hp_has_service_detail'     => $hasServiceDetail,
            'hp_has_company_profile'    => $hasCompanyProfile,
            'hp_has_owner_name'         => $hasOwnerName,
            'hp_has_local_keyword'      => $hasLocalKeyword,
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

    private function extractContactEmail(string $html): ?string
    {
        // 1. mailto: リンクを最優先（%40 等URLエンコード対応）
        if (preg_match('/href=["\']mailto:([^"\'?\s]+)/i', $html, $m)) {
            $decoded = urldecode($m[1]);
            if (preg_match('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $decoded, $em)) {
                return $em[0];
            }
        }
        // 2. fallback: script/style除去 → HTMLエンティティデコード → テキスト検索
        $text = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text, $m)) {
            foreach ($m[0] as $email) {
                if (!preg_match('/\.(png|jpg|gif|svg|webp|css|js|woff2?)$/i', $email)) {
                    return $email;
                }
            }
        }
        return null;
    }

    private function extractContactFormUrl(string $html, string $htmlLower, bool $hasContactForm, string $baseUrl): ?string
    {
        // 1. <form> のある問い合わせフォームが同一ページに存在する場合
        if ($hasContactForm) {
            if (preg_match('/<form[^>]+action=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
                $action = trim($m[1]);
                if ($action !== '' && $action !== '#') {
                    return mb_substr($this->resolveUrl($action, $baseUrl), 0, 500);
                }
            }
            return $baseUrl;
        }
        // 2. フォームなし → 問い合わせページへのリンクを探す（別ページにフォームがある場合）
        $keywords = ['contact', 'inquiry', 'enquiry', 'お問い合わせ', 'お問合せ', 'お問合わせ', '問い合わせ', '問合せ'];
        if (preg_match_all('/<a[^>]+href=["\']([^"\'#][^"\']*)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $href     = $match[1];
                $hrefLow  = strtolower($href);
                $textLow  = strtolower(strip_tags($match[2]));
                foreach ($keywords as $kw) {
                    if (str_contains($hrefLow, $kw) || str_contains($textLow, $kw)) {
                        return mb_substr($this->resolveUrl($href, $baseUrl), 0, 500);
                    }
                }
            }
        }
        return null;
    }

    private function resolveUrl(string $url, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host   = $parsed['host'] ?? '';
        return str_starts_with($url, '/')
            ? $scheme . '://' . $host . $url
            : rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }

    private function extractContactPhone(string $html): ?string
    {
        // 1. tel: リンクを優先（数字10〜11桁であることを検証）
        if (preg_match('/href=["\']tel:([+\d\-\s().]+)["\']/', $html, $m)) {
            $digits = preg_replace('/[^\d]/', '', $m[1]);
            if (strlen($digits) >= 10 && strlen($digits) <= 11) {
                return preg_replace('/[^\d\-+]/', '', trim($m[1]));
            }
        }
        // 2. テキスト検索: 日本の電話番号パターンに限定（日付誤検出を排除）
        //    各パターンは合計桁数が確定しているため YYYY-MM-DD 形式とは一致しない
        $patterns = [
            '/(?<!\d)0[2-9][-\s]\d{4}[-\s]\d{4}(?!\d)/',      // 03-XXXX-XXXX, 06-XXXX-XXXX (10桁)
            '/(?<!\d)0\d{2}[-\s]\d{3}[-\s]\d{4}(?!\d)/',       // 045-XXX-XXXX (10桁)
            '/(?<!\d)0\d{3}[-\s]\d{2}[-\s]\d{4}(?!\d)/',       // 0266-XX-XXXX (10桁)
            '/(?<!\d)0\d{4}[-\s]\d[-\s]\d{4}(?!\d)/',          // 01234-X-XXXX (10桁)
            '/(?<!\d)0[789]0[-\s]\d{4}[-\s]\d{4}(?!\d)/',      // 070/080/090-XXXX-XXXX (11桁)
            '/(?<!\d)0120[-\s]\d{3}[-\s]\d{3}(?!\d)/',         // フリーダイヤル0120 (10桁)
            '/(?<!\d)0800[-\s]\d{3}[-\s]\d{4}(?!\d)/',         // フリーダイヤル0800 (11桁)
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                return $m[0];
            }
        }
        return null;
    }

    private function detectJsRendering(string $html): bool
    {
        if (mb_strlen($html) < 1000) {
            return true;
        }

        $htmlLower = strtolower($html);
        $markers = [
            '<div id="app">',
            '<div id="root">',
            'window.__next_data__',
            'ng-version',
            '<div id="__nuxt">',
            'data-reactroot',
            'data-v-app',
        ];

        foreach ($markers as $marker) {
            if (str_contains($htmlLower, $marker)) {
                $bodyText = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
                $bodyText = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $bodyText);
                $bodyText = trim(strip_tags($bodyText));
                if (mb_strlen($bodyText) < 500) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }

    private function recordObservations(Company $company, array $factData): void
    {
        if (!$company->industry_id) {
            return;
        }

        $company->loadMissing('municipality.prefecture');
        $prefectureId = $company->municipality?->prefecture?->id;

        $axes = [
            'hp_improvement_score'    => $factData['hp_improvement_score'] ?? null,
            'ssl_enabled'             => isset($factData['ssl_enabled']) ? ($factData['ssl_enabled'] ? 1 : 0) : null,
            'mobile_friendly'         => isset($factData['mobile_friendly']) ? ($factData['mobile_friendly'] ? 1 : 0) : null,
            'update_status'           => $this->updateStatusToInt($factData['update_status'] ?? null),
            'cms_type'                => $this->cmsTypeToInt($factData['cms_type'] ?? null),
            'portal_dependency_level' => $this->portalLevelToInt($factData['portal_dependency_level'] ?? null),
        ];

        foreach ($axes as $axisKey => $value) {
            if ($value === null) {
                continue;
            }

            IndustryScoreObservation::updateOrCreate(
                [
                    'industry_id'       => $company->industry_id,
                    'axis_key'          => $axisKey,
                    'source_company_id' => $company->id,
                ],
                [
                    'value'         => $value,
                    'prefecture_id' => $prefectureId,
                    'region_id'     => null,
                    'observed_at'   => now(),
                ]
            );
        }
    }

    private function updateStatusToInt(?string $status): ?int
    {
        return match ($status) {
            'active'         => 3,
            'partial_active' => 2,
            'stale_1y'       => 1,
            'stale_2y'       => 0,
            default          => null,
        };
    }

    private function cmsTypeToInt(?string $cmsType): ?int
    {
        if ($cmsType === null) {
            return null;
        }
        return match ($cmsType) {
            'unknown' => 0,
            'builder' => 2,
            'wordpress', 'wix', 'squarespace' => 3,
            default   => 1,
        };
    }

    private function portalLevelToInt(?string $level): ?int
    {
        return match ($level) {
            'none'   => 0,
            'low'    => 1,
            'medium' => 2,
            'high'   => 3,
            default  => null,
        };
    }
}
