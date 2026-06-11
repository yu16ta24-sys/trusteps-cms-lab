<?php

namespace App\Services\Discovery;

use App\Models\DirectorySource;
use App\Models\DirectorySourcePage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DirectorySourceCrawlerService
{
    /**
     * @return array<string,mixed>
     */
    public function crawl(DirectorySource $directorySource, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $url = trim((string) $directorySource->url);

        if ($url === '') {
            $directorySource->forceFill([
                'status' => 'no_url',
                'crawl_status' => 'no_url',
                'last_crawled_at' => now(),
                'last_error' => 'URLがないため探索不可。',
            ])->save();

            return [
                'ok' => false,
                'created_or_updated' => 0,
                'candidates' => [],
                'message' => 'URLがないため探索しなかった。',
            ];
        }

        try {
            $response = Http::timeout(12)
                ->connectTimeout(6)
                ->withHeaders([
                    'User-Agent' => 'TRUSTEPS-CMS-Lab-DirectorySourceCrawler/0.18.9.8',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($url);
        } catch (Throwable $e) {
            $message = Str::limit($e->getMessage(), 1000, '');
            $directorySource->forceFill([
                'crawl_status' => 'fetch_error',
                'last_crawled_at' => now(),
                'last_error' => $message,
            ])->save();

            return [
                'ok' => false,
                'created_or_updated' => 0,
                'candidates' => [],
                'message' => '取得エラー：' . $message,
            ];
        }

        $status = $response->status();
        $html = (string) $response->body();

        if ($status < 200 || $status >= 400 || trim($html) === '') {
            $directorySource->forceFill([
                'crawl_status' => 'fetch_error',
                'last_crawled_at' => now(),
                'last_error' => 'HTTP ' . $status . ' / HTML空',
            ])->save();

            return [
                'ok' => false,
                'created_or_updated' => 0,
                'candidates' => [],
                'message' => 'HTTP ' . $status . ' のため探索できなかった。',
            ];
        }

        $candidates = $this->extractCandidateLinks($html, $url, $directorySource->normalized_domain, $limit);
        $createdOrUpdated = 0;

        foreach ($candidates as $candidate) {
            DirectorySourcePage::updateOrCreate(
                [
                    'directory_source_id' => $directorySource->id,
                    'url_hash' => sha1((string) $candidate['url']),
                ],
                [
                    'url' => $candidate['url'],
                    'normalized_domain' => $candidate['normalized_domain'],
                    'title' => $candidate['title'],
                    'link_text' => $candidate['link_text'],
                    'page_type' => $candidate['page_type'],
                    'status' => 'candidate',
                    'score' => $candidate['score'],
                    'confidence' => $candidate['confidence'],
                    'discovered_from' => $url,
                    'raw_json' => [
                        'crawler_version' => '0.18.9.8',
                        'matched_keywords' => $candidate['matched_keywords'],
                        'negative_keywords' => $candidate['negative_keywords'],
                        'href' => $candidate['href'],
                    ],
                    'last_seen_at' => now(),
                ]
            );
            $createdOrUpdated++;
        }

        $crawlStatus = $createdOrUpdated > 0 ? 'candidate_found' : 'no_candidate';
        $directorySource->forceFill([
            'crawl_status' => $crawlStatus,
            'last_crawled_at' => now(),
            'last_error' => null,
            'raw_json' => array_merge($directorySource->raw_json ?? [], [
                'latest_crawl' => [
                    'crawler_version' => '0.18.9.8',
                    'http_status' => $status,
                    'candidate_count' => $createdOrUpdated,
                    'crawled_at' => now()->toDateTimeString(),
                ],
            ]),
        ])->save();

        return [
            'ok' => true,
            'created_or_updated' => $createdOrUpdated,
            'candidates' => $candidates,
            'message' => $createdOrUpdated > 0
                ? "会員一覧候補を {$createdOrUpdated} 件見つけた。"
                : '会員一覧候補は見つからなかった。',
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function extractCandidateLinks(string $html, string $baseUrl, ?string $baseDomain, int $limit): array
    {
        $baseDomain = $baseDomain ?: $this->normalizeDomain($baseUrl);
        $links = [];

        if (!preg_match_all('/<a\s+[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/isu', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $href = html_entity_decode(trim((string) ($match[2] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $linkText = $this->cleanText(strip_tags((string) ($match[3] ?? '')));
            $absoluteUrl = $this->absoluteUrl($href, $baseUrl);

            if ($absoluteUrl === null) {
                continue;
            }

            $domain = $this->normalizeDomain($absoluteUrl);
            if ($baseDomain && $domain !== $baseDomain) {
                continue;
            }

            $scoreInfo = $this->scoreCandidate($absoluteUrl, $linkText);
            if ($scoreInfo['score'] < 35) {
                continue;
            }

            $links[$this->urlKey($absoluteUrl)] = [
                'url' => $absoluteUrl,
                'href' => $href,
                'normalized_domain' => $domain,
                'title' => Str::limit($linkText ?: $absoluteUrl, 255, ''),
                'link_text' => Str::limit($linkText, 255, ''),
                'page_type' => 'member_list_candidate',
                'score' => min(100, $scoreInfo['score']),
                'confidence' => $scoreInfo['score'] >= 70 ? 'high' : ($scoreInfo['score'] >= 50 ? 'medium' : 'low'),
                'matched_keywords' => $scoreInfo['matched'],
                'negative_keywords' => $scoreInfo['negative'],
            ];
        }

        return collect($links)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return array{score:int,matched:array<int,string>,negative:array<int,string>} */
    private function scoreCandidate(string $url, string $linkText): array
    {
        $target = mb_strtolower($url . ' ' . $linkText);
        $score = 0;
        $matched = [];
        $negative = [];

        $strong = [
            '会員一覧', '会員事業所', '事業所一覧', '事業者一覧', '企業一覧', '店舗一覧',
            '会員紹介', '事業者紹介', '企業紹介', 'お店紹介', '加盟店一覧', '加盟店紹介',
            'members', 'member', 'shoplist', 'shops', 'company', 'companies', 'kaiin', 'jigyousho', 'jigyosha',
        ];
        foreach ($strong as $keyword) {
            if (str_contains($target, mb_strtolower($keyword))) {
                $score += 35;
                $matched[] = $keyword;
            }
        }

        $medium = ['会員', '事業所', '事業者', '企業', '店舗', 'お店', '商店', '商業', '工業', '部会', '青年部', '女性部', '地域のお店', 'サービス'];
        foreach ($medium as $keyword) {
            if (str_contains($target, mb_strtolower($keyword))) {
                $score += 14;
                $matched[] = $keyword;
            }
        }

        $negativeKeywords = [
            '入会', '経営相談', '相談', '補助金', '融資', '共済', '労働保険', 'セミナー', '講習',
            'アクセス', 'お問い合わせ', '問合せ', 'プライバシー', '個人情報', 'サイトマップ', 'リンク集',
            'about', 'contact', 'privacy', 'sitemap', 'access', 'news', 'blog', 'event', 'seminar', 'join',
        ];
        foreach ($negativeKeywords as $keyword) {
            if (str_contains($target, mb_strtolower($keyword))) {
                $score -= 18;
                $negative[] = $keyword;
            }
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if ($path === '' || $path === '/') {
            $score -= 40;
            $negative[] = 'トップページ';
        }

        if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|css|js|pdf|zip|xlsx?|docx?)($|\?)/i', $url)) {
            $score -= 50;
            $negative[] = 'ファイルリンク';
        }

        return [
            'score' => max(0, $score),
            'matched' => array_values(array_unique($matched)),
            'negative' => array_values(array_unique($negative)),
        ];
    }

    private function absoluteUrl(string $href, string $baseUrl): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }
        if (preg_match('/^(javascript:|mailto:|tel:)/i', $href)) {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $href)) {
            return $this->normalizeUrlForStorage($href);
        }
        if (str_starts_with($href, '//')) {
            $scheme = (string) (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https');
            return $this->normalizeUrlForStorage($scheme . ':' . $href);
        }

        $parts = parse_url($baseUrl);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? '');
        if ($host === '') {
            return null;
        }

        $basePath = (string) ($parts['path'] ?? '/');
        if (!str_starts_with($href, '/')) {
            $baseDir = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
            $href = ($baseDir === '' ? '' : $baseDir) . '/' . $href;
        }

        return $this->normalizeUrlForStorage($scheme . '://' . $host . '/' . ltrim($href, '/'));
    }

    private function normalizeUrlForStorage(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = preg_replace('/#.*$/', '', $url) ?: $url;
        return $url;
    }

    private function normalizeDomain(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        $host = strtolower($host);
        $host = preg_replace('/^www\./i', '', $host) ?: $host;
        return $host ?: null;
    }

    private function cleanText(?string $value): string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';
        return trim($value);
    }

    private function urlKey(string $url): string
    {
        return mb_strtolower(rtrim($url, '/'));
    }
}
