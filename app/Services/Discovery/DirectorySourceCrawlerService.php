<?php

namespace App\Services\Discovery;

use App\Models\DirectorySource;
use App\Models\DirectorySourcePage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DirectorySourceCrawlerService
{
    private const CRAWLER_VERSION = '0.19.1';

    /** @return array<string,mixed> */
    public function crawl(DirectorySource $directorySource, int $limit = 50): array
    {
        $limit = max(10, min(250, $limit));
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
                'external_candidates' => 0,
                'internal_candidates' => 0,
                'message' => 'URLがないため探索しなかった。',
            ];
        }

        $home = $this->fetchHtml($url);
        if (! $home['ok']) {
            $directorySource->forceFill([
                'crawl_status' => 'fetch_error',
                'last_crawled_at' => now(),
                'last_error' => $home['message'],
            ])->save();

            return [
                'ok' => false,
                'created_or_updated' => 0,
                'external_candidates' => 0,
                'internal_candidates' => 0,
                'message' => $home['message'],
            ];
        }

        $baseUrl = (string) ($home['final_url'] ?: $url);
        $baseDomain = $directorySource->normalized_domain ?: $this->normalizeDomain($baseUrl);
        $scopePrefix = $this->siteScopePrefix($baseUrl, $baseDomain);
        $homeHtml = (string) $home['html'];
        $homeSignals = $this->extractPageSignals($homeHtml);

        $internalMap = [];
        $externalMap = [];
        $debug = [
            'home_http_status' => $home['status'],
            'home_url' => $baseUrl,
            'base_domain' => $baseDomain,
            'scope_prefix' => $scopePrefix,
            'top_internal_links' => 0,
            'home_external_links' => 0,
            'probe_pages' => 0,
            'probe_errors' => 0,
            'external_links_found' => 0,
            'fallback_used' => false,
        ];

        $topInternalLinks = $this->extractInternalLinks($homeHtml, $baseUrl, $baseDomain, $scopePrefix);
        $debug['top_internal_links'] = count($topInternalLinks);

        foreach ($topInternalLinks as $link) {
            $scoreInfo = $this->scoreInternalDirectoryCandidate(
                $link['url'],
                $link['link_text'],
                $link['context_text'],
                '',
                '',
                'top_link'
            );

            $this->upsertCandidate($internalMap, $link, $scoreInfo, [
                'source_stage' => 'top_link',
                'page_title' => $homeSignals['title'],
                'page_headings' => $homeSignals['headings'],
                'candidate_kind' => 'internal_directory_page',
            ]);
        }

        $homeExternalLinks = $this->extractExternalBusinessLinks($homeHtml, $baseUrl, $baseDomain, $scopePrefix);
        $debug['home_external_links'] = count($homeExternalLinks);
        foreach ($homeExternalLinks as $externalLink) {
            $scoreInfo = $this->scoreExternalBusinessCandidate($externalLink, 35, $homeSignals, 'home_external_link');
            if ((int) $scoreInfo['score'] >= 45) {
                $this->upsertCandidate($externalMap, $externalLink, $scoreInfo, [
                    'source_stage' => 'home_external_link',
                    'page_title' => $homeSignals['title'],
                    'page_headings' => $homeSignals['headings'],
                    'candidate_kind' => 'external_business_site',
                ]);
            }
        }

        $probeLinks = collect($internalMap)
            ->filter(fn (array $candidate): bool => (int) $candidate['score'] >= 8)
            ->sortByDesc('score')
            ->take(80)
            ->values()
            ->all();

        foreach ($probeLinks as $probe) {
            $probeUrl = (string) $probe['url'];
            if ($probeUrl === '' || $this->isLikelyFileUrl($probeUrl)) {
                continue;
            }

            $probeResponse = $this->fetchHtml($probeUrl);
            if (! $probeResponse['ok']) {
                $debug['probe_errors']++;
                continue;
            }

            $debug['probe_pages']++;
            $probeHtml = (string) $probeResponse['html'];
            $probeSignals = $this->extractPageSignals($probeHtml);
            $probeText = $this->cleanText($probeSignals['title'] . ' ' . implode(' ', $probeSignals['headings']) . ' ' . $probeSignals['meta_description'] . ' ' . $probeSignals['body_excerpt']);

            $probeScore = $this->scoreInternalDirectoryCandidate(
                $probeUrl,
                (string) ($probe['link_text'] ?? ''),
                $probeText,
                $probeSignals['title'],
                implode(' ', $probeSignals['headings']),
                'probed_page'
            );

            $this->upsertCandidate($internalMap, [
                'url' => $probeUrl,
                'href' => $probe['href'] ?? $probeUrl,
                'normalized_domain' => $this->normalizeDomain($probeUrl),
                'link_text' => (string) ($probe['link_text'] ?? ''),
                'context_text' => Str::limit($probeText, 1200, ''),
                'discovered_from' => $baseUrl,
            ], $probeScore, [
                'source_stage' => 'probed_page',
                'http_status' => $probeResponse['status'],
                'page_title' => $probeSignals['title'],
                'page_headings' => $probeSignals['headings'],
                'candidate_kind' => 'internal_directory_page',
            ]);

            $externalLinks = $this->extractExternalBusinessLinks($probeHtml, $probeUrl, $baseDomain, $scopePrefix);
            $debug['external_links_found'] += count($externalLinks);
            foreach ($externalLinks as $externalLink) {
                $scoreInfo = $this->scoreExternalBusinessCandidate($externalLink, (int) $probeScore['score'], $probeSignals, 'probed_external_link');
                if ((int) $scoreInfo['score'] < 40) {
                    continue;
                }

                $this->upsertCandidate($externalMap, $externalLink, $scoreInfo, [
                    'source_stage' => 'probed_external_link',
                    'parent_url' => $probeUrl,
                    'parent_title' => $probeSignals['title'],
                    'page_title' => $probeSignals['title'],
                    'page_headings' => $probeSignals['headings'],
                    'candidate_kind' => 'external_business_site',
                ]);
            }

            $secondaryLinks = $this->extractInternalLinks($probeHtml, $probeUrl, $baseDomain, $scopePrefix);
            foreach ($secondaryLinks as $secondaryLink) {
                $secondaryScore = $this->scoreInternalDirectoryCandidate(
                    $secondaryLink['url'],
                    $secondaryLink['link_text'],
                    $secondaryLink['context_text'] . ' ' . $probeText,
                    $probeSignals['title'],
                    implode(' ', $probeSignals['headings']),
                    'secondary_link'
                );

                $this->upsertCandidate($internalMap, $secondaryLink, $secondaryScore, [
                    'source_stage' => 'secondary_link',
                    'parent_url' => $probeUrl,
                    'parent_title' => $probeSignals['title'],
                    'candidate_kind' => 'internal_directory_page',
                ]);
            }
        }

        $externalCandidates = collect($externalMap)
            ->filter(fn (array $candidate): bool => (int) $candidate['score'] >= 40)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();

        $remainingLimit = max(0, $limit - count($externalCandidates));
        $internalCandidates = collect($internalMap)
            ->filter(fn (array $candidate): bool => (int) $candidate['score'] >= 24)
            ->sortByDesc('score')
            ->take($remainingLimit)
            ->values()
            ->all();

        if (empty($externalCandidates) && empty($internalCandidates)) {
            $debug['fallback_used'] = true;
            $internalCandidates = collect($internalMap)
                ->filter(fn (array $candidate): bool => (int) $candidate['score'] >= 5)
                ->sortByDesc('score')
                ->take(min(15, $limit))
                ->map(function (array $candidate): array {
                    $candidate['page_type'] = 'low_confidence_directory_candidate';
                    $candidate['confidence'] = 'low';
                    $candidate['raw_json']['fallback_reason'] = '高信頼候補が0件だったため、低信頼候補として残した。';
                    return $candidate;
                })
                ->values()
                ->all();
        }

        $createdOrUpdated = 0;
        foreach (array_merge($externalCandidates, $internalCandidates) as $candidate) {
            $page = DirectorySourcePage::firstOrNew([
                'directory_source_id' => $directorySource->id,
                'url_hash' => sha1((string) $candidate['url']),
            ]);

            $status = $page->exists && $page->status === 'exported_to_source_record'
                ? 'exported_to_source_record'
                : 'candidate';

            $rawJson = $candidate['raw_json'];
            if ($page->exists && is_array($page->raw_json) && isset($page->raw_json['exported_to_source_record_id'])) {
                $rawJson['exported_to_source_record_id'] = $page->raw_json['exported_to_source_record_id'];
                $rawJson['exported_at'] = $page->raw_json['exported_at'] ?? null;
            }

            $page->forceFill([
                'url' => $candidate['url'],
                'normalized_domain' => $candidate['normalized_domain'],
                'title' => $candidate['title'],
                'link_text' => $candidate['link_text'],
                'page_type' => $candidate['page_type'],
                'status' => $status,
                'score' => min(100, max(0, (int) $candidate['score'])),
                'confidence' => $candidate['confidence'],
                'discovered_from' => $candidate['discovered_from'],
                'raw_json' => $rawJson,
                'last_seen_at' => now(),
            ])->save();

            $createdOrUpdated++;
        }

        $crawlStatus = $createdOrUpdated > 0 ? 'candidate_found' : 'no_candidate';
        $directorySource->forceFill([
            'crawl_status' => $crawlStatus,
            'last_crawled_at' => now(),
            'last_error' => null,
            'raw_json' => array_merge($directorySource->raw_json ?? [], [
                'latest_crawl' => array_merge($debug, [
                    'crawler_version' => self::CRAWLER_VERSION,
                    'candidate_count' => $createdOrUpdated,
                    'external_candidate_count' => count($externalCandidates),
                    'internal_candidate_count' => count($internalCandidates),
                    'crawled_at' => now()->toDateTimeString(),
                ]),
            ]),
        ])->save();

        return [
            'ok' => true,
            'created_or_updated' => $createdOrUpdated,
            'external_candidates' => count($externalCandidates),
            'internal_candidates' => count($internalCandidates),
            'message' => "探索完了。事業者HP候補 {$this->formatCount(count($externalCandidates))} 件 / 内部ページ候補 {$this->formatCount(count($internalCandidates))} 件。探索範囲：トップ内部リンク {$debug['top_internal_links']} 件 / 追加取得 {$debug['probe_pages']} 件 / 外部リンク検出 {$debug['external_links_found']} 件。",
        ];
    }

    /** @return array{ok:bool,status:int|null,html:string,final_url:string|null,message:string} */
    private function fetchHtml(string $url): array
    {
        $url = $this->normalizeUrlForStorage($url);
        if (! preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $urlsToTry = [$url];
        if (str_starts_with(strtolower($url), 'https://')) {
            $urlsToTry[] = 'http://' . substr($url, 8);
        }

        $lastMessage = '';
        foreach (array_values(array_unique($urlsToTry)) as $targetUrl) {
            try {
                $response = Http::timeout(12)
                    ->connectTimeout(6)
                    ->withHeaders([
                        'User-Agent' => 'TRUSTEPS-CMS-Lab-DirectorySourceCrawler/' . self::CRAWLER_VERSION,
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    ])
                    ->get($targetUrl);
            } catch (Throwable $e) {
                $lastMessage = '取得エラー：' . Str::limit($e->getMessage(), 800, '');
                continue;
            }

            $status = $response->status();
            $contentType = strtolower((string) $response->header('Content-Type', ''));
            $body = (string) $response->body();
            $effectiveUrl = method_exists($response, 'effectiveUri') && $response->effectiveUri()
                ? (string) $response->effectiveUri()
                : $targetUrl;

            if ($status >= 200 && $status < 400 && trim($body) !== '' && ($contentType === '' || str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml'))) {
                return [
                    'ok' => true,
                    'status' => $status,
                    'html' => $body,
                    'final_url' => $effectiveUrl,
                    'message' => 'OK',
                ];
            }

            $lastMessage = 'HTTP ' . $status . ' / Content-Type ' . ($contentType ?: '-');
        }

        return [
            'ok' => false,
            'status' => null,
            'html' => '',
            'final_url' => null,
            'message' => $lastMessage ?: 'HTMLを取得できなかった。',
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function extractInternalLinks(string $html, string $baseUrl, ?string $baseDomain, ?string $scopePrefix): array
    {
        $baseDomain = $baseDomain ?: $this->normalizeDomain($baseUrl);
        $links = [];

        foreach ($this->extractAnchorMatches($html) as $match) {
            $href = html_entity_decode(trim((string) ($match['href'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $absoluteUrl = $this->absoluteUrl($href, $baseUrl);
            if ($absoluteUrl === null || $this->isLikelyFileUrl($absoluteUrl)) {
                continue;
            }

            $domain = $this->normalizeDomain($absoluteUrl);
            if (! $this->isInsideSameSite($absoluteUrl, $domain, $baseDomain, $scopePrefix)) {
                continue;
            }

            $key = $this->urlKey($absoluteUrl);
            $links[$key] = [
                'url' => $absoluteUrl,
                'href' => $href,
                'normalized_domain' => $domain,
                'link_text' => Str::limit($match['link_text'], 255, ''),
                'context_text' => Str::limit($this->anchorContext($html, (int) $match['offset']), 1200, ''),
                'discovered_from' => $baseUrl,
            ];
        }

        return array_values($links);
    }

    /** @return array<int,array<string,mixed>> */
    private function extractExternalBusinessLinks(string $html, string $baseUrl, ?string $baseDomain, ?string $scopePrefix): array
    {
        $baseDomain = $baseDomain ?: $this->normalizeDomain($baseUrl);
        $links = [];

        foreach ($this->extractAnchorMatches($html) as $match) {
            $href = html_entity_decode(trim((string) ($match['href'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $absoluteUrl = $this->absoluteUrl($href, $baseUrl);
            if ($absoluteUrl === null || $this->isLikelyFileUrl($absoluteUrl)) {
                continue;
            }

            $domain = $this->normalizeDomain($absoluteUrl);
            if (! $domain || $this->isKnownNonBusinessDomain($domain)) {
                continue;
            }

            if ($this->isInsideSameSite($absoluteUrl, $domain, $baseDomain, $scopePrefix)) {
                continue;
            }

            // r.goope.jp などの共有ホストだけは、別テナントの可能性があるため同一ドメインでも対象にする。
            if ($domain === $baseDomain && ! $this->isSharedHost($domain)) {
                continue;
            }

            $contextText = $this->anchorContext($html, (int) $match['offset']);
            if ($this->looksLikeAdministrativeExternalLink($match['link_text'], $contextText, $absoluteUrl)) {
                continue;
            }

            $key = $this->urlKey($absoluteUrl);
            $links[$key] = [
                'url' => $absoluteUrl,
                'href' => $href,
                'normalized_domain' => $domain,
                'link_text' => Str::limit($match['link_text'], 255, ''),
                'context_text' => Str::limit($contextText, 1200, ''),
                'discovered_from' => $baseUrl,
            ];
        }

        return array_values($links);
    }

    /** @return array<int,array{href:string,link_text:string,offset:int}> */
    private function extractAnchorMatches(string $html): array
    {
        if (! preg_match_all('/<a\s+[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/isu', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $anchors = [];
        foreach ($matches as $match) {
            $anchors[] = [
                'href' => (string) ($match[2][0] ?? ''),
                'link_text' => $this->cleanText(strip_tags((string) ($match[3][0] ?? ''))),
                'offset' => (int) ($match[0][1] ?? 0),
            ];
        }

        return $anchors;
    }

    private function anchorContext(string $html, int $offset): string
    {
        return $this->cleanText(strip_tags(substr($html, max(0, $offset - 700), 1800)));
    }

    /** @return array{title:string,meta_description:string,headings:array<int,string>,body_excerpt:string} */
    private function extractPageSignals(string $html): array
    {
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/isu', $html, $match)) {
            $title = $this->cleanText($match[1] ?? '');
        }

        $metaDescription = '';
        if (preg_match('/<meta\s+[^>]*name\s*=\s*(["\'])description\1[^>]*content\s*=\s*(["\'])(.*?)\2[^>]*>/isu', $html, $match)
            || preg_match('/<meta\s+[^>]*content\s*=\s*(["\'])(.*?)\1[^>]*name\s*=\s*(["\'])description\3[^>]*>/isu', $html, $match)) {
            $metaDescription = $this->cleanText($match[3] ?? $match[2] ?? '');
        }

        $headings = [];
        if (preg_match_all('/<h[1-3][^>]*>(.*?)<\/h[1-3]>/isu', $html, $matches)) {
            foreach ($matches[1] as $headingHtml) {
                $heading = $this->cleanText(strip_tags((string) $headingHtml));
                if ($heading !== '') {
                    $headings[] = Str::limit($heading, 120, '');
                }
            }
        }

        $bodyText = $this->cleanText(strip_tags(preg_replace('/<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>/isu', ' ', $html) ?: $html));

        return [
            'title' => Str::limit($title, 255, ''),
            'meta_description' => Str::limit($metaDescription, 500, ''),
            'headings' => array_slice(array_values(array_unique($headings)), 0, 12),
            'body_excerpt' => Str::limit($bodyText, 4000, ''),
        ];
    }

    /** @return array{score:int,matched:array<int,string>,negative:array<int,string>,page_type:string,confidence:string} */
    private function scoreInternalDirectoryCandidate(string $url, string $linkText, string $contextText = '', string $pageTitle = '', string $headings = '', string $stage = 'link'): array
    {
        $urlLower = mb_strtolower($url);
        $linkLower = mb_strtolower($linkText);
        $contextLower = mb_strtolower($contextText);
        $pageLower = mb_strtolower($pageTitle . ' ' . $headings);
        $all = $urlLower . ' ' . $linkLower . ' ' . $contextLower . ' ' . $pageLower;

        $score = 0;
        $matched = [];
        $negative = [];

        $strong = [
            '会員一覧', '会員名簿', '会員紹介', '会員事業所', '会員企業', '会員検索', '会員店舗',
            '事業所一覧', '事業所検索', '事業者一覧', '事業者紹介', '企業一覧', '企業紹介', '企業検索',
            '店舗一覧', '店舗紹介', '店舗検索', '加盟店', '加盟店一覧', '加盟店紹介', 'お店紹介', 'お店を探す',
            '商工会会員', 'member list', 'members', 'member', 'shoplist', 'shops', 'shop', 'company', 'companies',
            'kaiin', 'jigyousho', 'jigyosha', 'kigyo', 'tenpo', 'kameiten', 'omise', 'list', 'search',
        ];
        foreach ($strong as $keyword) {
            $keywordLower = mb_strtolower($keyword);
            if (str_contains($urlLower . ' ' . $linkLower . ' ' . $pageLower, $keywordLower)) {
                $score += 35;
                $matched[] = $keyword;
            } elseif (str_contains($contextLower, $keywordLower)) {
                $score += 18;
                $matched[] = $keyword;
            }
        }

        $medium = [
            'お店', '店舗', '事業所', '事業者', '企業', '商店', '商業', '工業', 'サービス', '地域のお店', 'まちのお店',
            '買う', '食べる', '暮らす', '泊まる', '観光', '特産品', '逸品', '商品', 'グルメ', 'ショッピング',
            '部会員', '商業部会', '工業部会', '青年部', '女性部',
        ];
        foreach ($medium as $keyword) {
            $keywordLower = mb_strtolower($keyword);
            if (str_contains($all, $keywordLower)) {
                $score += 12;
                $matched[] = $keyword;
            }
        }

        $negativeKeywords = [
            '入会案内', '入会', '経営相談', '相談', '補助金', '融資', '共済', '労働保険', 'セミナー', '講習',
            'アクセス', 'お問い合わせ', '問合せ', 'プライバシー', '個人情報', 'サイトマップ', '採用', '求人',
            'about', 'contact', 'privacy', 'sitemap', 'access', 'recruit', 'join', 'seminar', 'login', 'admin', 'wp-login',
        ];
        foreach ($negativeKeywords as $keyword) {
            $keywordLower = mb_strtolower($keyword);
            if (str_contains($all, $keywordLower)) {
                $score -= 14;
                $negative[] = $keyword;
            }
        }

        if (preg_match('/\/(member|members|shop|shops|shoplist|company|companies|kaiin|jigyousho|jigyosha|kigyo|tenpo|kameiten|omise|search|list)(\/|$|\?|\.)/i', $url)) {
            $score += 24;
            $matched[] = 'URLパス候補語';
        }

        if ($stage === 'probed_page') {
            $score += 8;
        } elseif ($stage === 'secondary_link') {
            $score += 5;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if ($path === '' || $path === '/') {
            $score -= 35;
            $negative[] = 'トップページ';
        }

        if ($this->isLikelyFileUrl($url)) {
            $score -= 60;
            $negative[] = 'ファイルリンク';
        }

        $score = max(0, min(100, $score));
        $pageType = $score >= 45 ? 'member_list_page_candidate' : 'low_confidence_directory_candidate';
        $confidence = $score >= 75 ? 'high' : ($score >= 45 ? 'medium' : 'low');

        return [
            'score' => $score,
            'matched' => array_values(array_unique($matched)),
            'negative' => array_values(array_unique($negative)),
            'page_type' => $pageType,
            'confidence' => $confidence,
        ];
    }

    /** @param array<string,mixed> $link @param array<string,mixed> $pageSignals @return array{score:int,matched:array<int,string>,negative:array<int,string>,page_type:string,confidence:string} */
    private function scoreExternalBusinessCandidate(array $link, int $parentScore, array $pageSignals, string $stage): array
    {
        $url = (string) ($link['url'] ?? '');
        $domain = (string) ($link['normalized_domain'] ?? $this->normalizeDomain($url));
        $linkText = (string) ($link['link_text'] ?? '');
        $contextText = (string) ($link['context_text'] ?? '');
        $all = mb_strtolower($url . ' ' . $domain . ' ' . $linkText . ' ' . $contextText . ' ' . ($pageSignals['title'] ?? '') . ' ' . implode(' ', $pageSignals['headings'] ?? []));

        $score = $stage === 'home_external_link' ? 48 : 62;
        $matched = ['外部ドメイン'];
        $negative = [];

        if ($parentScore >= 60) {
            $score += 16;
            $matched[] = '高スコア名簿ページ由来';
        } elseif ($parentScore >= 35) {
            $score += 8;
            $matched[] = '名簿ページ由来';
        }

        foreach (['ホームページ', 'HP', '公式', 'website', 'web site', 'URL', 'リンク'] as $keyword) {
            if (str_contains($all, mb_strtolower($keyword))) {
                $score += 10;
                $matched[] = $keyword;
            }
        }

        if ($this->isBuilderDomain($domain)) {
            $score += 8;
            $matched[] = '簡易ビルダー系';
        }

        foreach (['facebook', 'instagram', 'twitter', 'youtube', 'line', '地図', 'map', 'google'] as $keyword) {
            if (str_contains($all, $keyword)) {
                $score -= 25;
                $negative[] = $keyword;
            }
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'matched' => array_values(array_unique($matched)),
            'negative' => array_values(array_unique($negative)),
            'page_type' => 'member_site_candidate',
            'confidence' => $score >= 80 ? 'high' : ($score >= 55 ? 'medium' : 'low'),
        ];
    }

    /** @param array<string,array<string,mixed>> $candidateMap @param array<string,mixed> $link @param array<string,mixed> $scoreInfo @param array<string,mixed> $extra */
    private function upsertCandidate(array &$candidateMap, array $link, array $scoreInfo, array $extra = []): void
    {
        $url = (string) ($link['url'] ?? '');
        if ($url === '') {
            return;
        }

        $key = $this->urlKey($url);
        $score = (int) ($scoreInfo['score'] ?? 0);
        if (isset($candidateMap[$key]) && (int) $candidateMap[$key]['score'] >= $score) {
            return;
        }

        $title = '';
        if (($extra['candidate_kind'] ?? null) === 'external_business_site') {
            $title = (string) ($link['link_text'] ?: ($link['normalized_domain'] ?? $url));
        } elseif (($extra['source_stage'] ?? null) === 'probed_page') {
            $title = (string) ($extra['page_title'] ?? '');
        }
        if ($title === '') {
            $title = (string) ($link['link_text'] ?? $url);
        }

        $candidateMap[$key] = [
            'url' => $url,
            'href' => (string) ($link['href'] ?? $url),
            'normalized_domain' => $link['normalized_domain'] ?? $this->normalizeDomain($url),
            'title' => Str::limit($title, 255, ''),
            'link_text' => Str::limit((string) ($link['link_text'] ?? ''), 255, ''),
            'page_type' => $scoreInfo['page_type'] ?? 'member_list_page_candidate',
            'score' => $score,
            'confidence' => $scoreInfo['confidence'] ?? 'low',
            'discovered_from' => (string) ($link['discovered_from'] ?? $extra['parent_url'] ?? ''),
            'raw_json' => [
                'crawler_version' => self::CRAWLER_VERSION,
                'candidate_kind' => $extra['candidate_kind'] ?? 'internal_directory_page',
                'source_stage' => $extra['source_stage'] ?? 'unknown',
                'matched_keywords' => $scoreInfo['matched'] ?? [],
                'negative_keywords' => $scoreInfo['negative'] ?? [],
                'href' => (string) ($link['href'] ?? ''),
                'context_text' => Str::limit((string) ($link['context_text'] ?? ''), 1000, ''),
                'page_title' => $extra['page_title'] ?? null,
                'page_headings' => $extra['page_headings'] ?? null,
                'parent_url' => $extra['parent_url'] ?? null,
                'parent_title' => $extra['parent_title'] ?? null,
                'http_status' => $extra['http_status'] ?? null,
            ],
        ];
    }

    private function absoluteUrl(string $href, string $baseUrl): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }
        if (preg_match('/^(javascript:|mailto:|tel:|data:)/i', $href)) {
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
        if (! str_starts_with($href, '/')) {
            $baseDir = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
            $href = ($baseDir === '' || $baseDir === '.' ? '' : $baseDir) . '/' . $href;
        }

        return $this->normalizeUrlForStorage($scheme . '://' . $host . '/' . ltrim($href, '/'));
    }

    private function normalizeUrlForStorage(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = preg_replace('/#.*$/', '', $url) ?: $url;
        $url = preg_replace('/\s+/u', '', $url) ?: $url;
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

    private function isInsideSameSite(string $url, ?string $domain, ?string $baseDomain, ?string $scopePrefix): bool
    {
        if (! $domain || ! $baseDomain || $domain !== $baseDomain) {
            return false;
        }

        if ($scopePrefix === null || $scopePrefix === '') {
            return true;
        }

        $path = '/' . ltrim((string) (parse_url($url, PHP_URL_PATH) ?? '/'), '/');
        return $path === '/' . trim($scopePrefix, '/') || str_starts_with($path, '/' . trim($scopePrefix, '/') . '/');
    }

    private function siteScopePrefix(string $url, ?string $domain): ?string
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/');
        if ($path === '') {
            return null;
        }

        $first = explode('/', $path)[0] ?? '';
        if ($first === '' || preg_match('/^(index\.php|index\.html?)$/i', $first)) {
            return null;
        }

        if ($this->isSharedHost((string) $domain)) {
            return $first;
        }

        return $first;
    }

    private function isSharedHost(string $domain): bool
    {
        return in_array($domain, ['r.goope.jp', 'goope.jp', 'peraichi.com', 'jimdosite.com', 'jimdofree.com', 'wixsite.com'], true);
    }

    private function isBuilderDomain(string $domain): bool
    {
        return $this->isSharedHost($domain) || str_contains($domain, 'wixsite.com') || str_contains($domain, 'jimdofree.com');
    }

    private function isKnownNonBusinessDomain(string $domain): bool
    {
        $blocked = [
            'facebook.com', 'instagram.com', 'twitter.com', 'x.com', 'youtube.com', 'youtu.be', 'line.me', 'lin.ee',
            'google.com', 'google.co.jp', 'maps.google.com', 'goo.gl', 'g.page', 'apple.com', 'amazon.co.jp', 'amazon.com',
            'rakuten.co.jp', 'yahoo.co.jp', 'shokokai.or.jp', 'jcci.or.jp', 'nta.go.jp', 'meti.go.jp', 'mhlw.go.jp',
            'pref.', 'city.', 'town.', 'village.', 'wordpress.org', 'w3.org', 'googleapis.com', 'gstatic.com', 'jsdelivr.net',
        ];
        foreach ($blocked as $needle) {
            if (str_contains($domain, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function looksLikeAdministrativeExternalLink(string $linkText, string $contextText, string $url): bool
    {
        $all = mb_strtolower($linkText . ' ' . $contextText . ' ' . $url);
        foreach (['行政', '役場', '市役所', '県庁', '商工会連合会', '全国連', '補助金', '共済', '税務署', '金融公庫'] as $keyword) {
            if (str_contains($all, mb_strtolower($keyword))) {
                return true;
            }
        }
        return false;
    }

    private function isLikelyFileUrl(string $url): bool
    {
        return (bool) preg_match('/\.(jpg|jpeg|png|gif|webp|svg|css|js|pdf|zip|xlsx?|docx?|pptx?|mp4|mov|avi|wmv)($|\?)/i', $url);
    }

    private function cleanText(?string $value): string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\x{00a0}/u', ' ', $value) ?: $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';
        return trim($value);
    }

    private function urlKey(string $url): string
    {
        return mb_strtolower(rtrim($url, '/'));
    }

    private function formatCount(int $count): string
    {
        return number_format($count);
    }
}
