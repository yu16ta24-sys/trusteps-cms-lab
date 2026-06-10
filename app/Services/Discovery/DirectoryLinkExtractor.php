<?php

namespace App\Services\Discovery;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DirectoryLinkExtractor
{
    public function extract(string $url, array $options = []): array
    {
        $sourceUrl = $this->normalizeUrl($url);
        if (!$sourceUrl) {
            return [
                'ok' => false,
                'error' => 'HTTP/HTTPSの名簿URLとして解釈できなかった。',
            ];
        }

        $robots = $this->checkRobots($sourceUrl);
        if (!$robots['allowed']) {
            return [
                'ok' => false,
                'error' => 'robots.txtでクロール対象外の可能性があるため取得を停止した。対象URLを確認して。',
                'warnings' => $robots['warnings'] ?? [],
            ];
        }

        $warnings = $robots['warnings'] ?? [];
        $fetched = $this->fetchHtml($sourceUrl);
        if (!$fetched['ok']) {
            return [
                'ok' => false,
                'error' => $fetched['error'] ?? '名簿URLへのHTTP取得に失敗した。',
                'warnings' => $warnings,
            ];
        }

        $warnings = array_merge($warnings, $fetched['warnings'] ?? []);
        $parsed = $this->parseLinks($fetched['html'], $sourceUrl, null);

        $followDetailPages = (bool) ($options['follow_detail_pages'] ?? false);
        $detailLimit = max(1, min(
            (int) ($options['detail_page_limit'] ?? config('discovery.directory_detail_page_limit', 20)),
            (int) config('discovery.directory_detail_page_hard_limit', 30)
        ));

        $links = $this->primaryLinks($parsed['links']);
        $detailCandidateCount = collect($parsed['links'])->where('candidate_type', 'directory_detail_candidate')->count();
        $detailStats = [
            'enabled' => $followDetailPages,
            'candidates' => $detailCandidateCount,
            'hidden_from_final_candidates' => $detailCandidateCount,
            'fetched' => 0,
            'external_links_found' => 0,
            'limit' => $detailLimit,
        ];

        if ($followDetailPages) {
            $detailResult = $this->extractFromDetailPages($parsed['links'], $sourceUrl, $detailLimit);
            $links = array_merge($links, $detailResult['links']);
            $warnings = array_merge($warnings, $detailResult['warnings']);
            $detailStats['fetched'] = $detailResult['fetched'];
            $detailStats['external_links_found'] = count($detailResult['links']);
        }

        $filterStats = [
            'duplicate_url_hidden' => 0,
            'duplicate_domain_hidden' => 0,
            'internal_hidden' => 0,
        ];
        $links = $this->dedupeAndLimit($links, $warnings, $filterStats);

        if (empty($links)) {
            $warnings[] = '候補リンクを抽出できなかった。JS描画ページ、PDF、または事業者詳細ページの構造が未対応の可能性。';
        }

        return [
            'ok' => true,
            'source_url' => $sourceUrl,
            'title' => $parsed['title'],
            'links' => $links,
            'warnings' => array_values(array_unique($warnings)),
            'detail_stats' => $detailStats,
            'filter_stats' => $filterStats,
        ];
    }

    private function fetchHtml(string $url): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('discovery.directory_user_agent', 'TRUSTEPS-CMS-Lab-DiscoveryBot/0.18.3.1'),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
                ->timeout((int) config('discovery.directory_timeout', 10))
                ->connectTimeout((int) config('discovery.directory_connect_timeout', 5))
                ->get($url);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => 'HTTP取得に失敗した: ' . $e->getMessage(),
                'warnings' => [],
            ];
        }

        if (!$response->successful()) {
            return [
                'ok' => false,
                'error' => 'HTTPステータスが正常ではない: ' . $response->status(),
                'warnings' => [],
            ];
        }

        $warnings = [];
        $contentType = strtolower((string) $response->header('Content-Type', ''));
        if ($contentType !== '' && !str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml')) {
            $warnings[] = 'Content-TypeがHTMLではない可能性: ' . $contentType;
        }

        return [
            'ok' => true,
            'html' => $this->toUtf8($response->body(), $contentType),
            'warnings' => $warnings,
        ];
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    private function checkRobots(string $sourceUrl): array
    {
        $parts = parse_url($sourceUrl);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return ['allowed' => true, 'warnings' => ['robots.txt確認をスキップした。']];
        }

        $robotsUrl = strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']) . '/robots.txt';
        $targetPath = (string) ($parts['path'] ?? '/');
        if ($targetPath === '') {
            $targetPath = '/';
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('discovery.directory_user_agent', 'TRUSTEPS-CMS-Lab-DiscoveryBot/0.18.3.1'),
            ])
                ->timeout(5)
                ->connectTimeout(3)
                ->get($robotsUrl);
        } catch (Throwable $e) {
            return ['allowed' => true, 'warnings' => ['robots.txtを取得できなかったため、通常の低頻度取得として継続: ' . $e->getMessage()]];
        }

        if (!$response->successful()) {
            return ['allowed' => true, 'warnings' => ['robots.txtが取得できなかったため、通常の低頻度取得として継続: HTTP ' . $response->status()]];
        }

        $appliesToStar = false;
        $disallows = [];
        $lines = preg_split('/\R/u', $response->body()) ?: [];

        foreach ($lines as $line) {
            $line = trim(preg_replace('/#.*/', '', (string) $line));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                $appliesToStar = $value === '*' || Str::contains(strtolower($value), 'trusteps');
                continue;
            }

            if ($appliesToStar && $field === 'disallow' && $value !== '') {
                $disallows[] = $value;
            }
        }

        foreach ($disallows as $disallow) {
            if ($disallow === '/') {
                return ['allowed' => false, 'warnings' => ['robots.txtで全体Disallowが指定されている。']];
            }

            if (str_starts_with($targetPath, $disallow)) {
                return ['allowed' => false, 'warnings' => ["robots.txt Disallowに該当: {$disallow}"]];
            }
        }

        return ['allowed' => true, 'warnings' => []];
    }

    private function toUtf8(string $body, string $contentType): string
    {
        $encoding = null;
        if (preg_match('/charset=([a-zA-Z0-9_\-]+)/i', $contentType, $matches)) {
            $encoding = strtoupper($matches[1]);
        }

        if (!$encoding && preg_match('/<meta[^>]+charset=["\']?\s*([a-zA-Z0-9_\-]+)/iu', substr($body, 0, 4096), $matches)) {
            $encoding = strtoupper($matches[1]);
        }

        if (!$encoding) {
            $encoding = mb_detect_encoding($body, ['UTF-8', 'SJIS-win', 'SJIS', 'EUC-JP', 'ISO-2022-JP'], true) ?: 'UTF-8';
        }

        if (strtoupper($encoding) !== 'UTF-8') {
            $converted = @mb_convert_encoding($body, 'UTF-8', $encoding);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return $body;
    }

    private function parseLinks(string $html, string $sourceUrl, ?string $detailPageUrl): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $titleNode = $xpath->query('//title')->item(0);
        $title = trim((string) ($titleNode?->textContent ?? ''));
        $links = [];
        $seen = [];
        $sourceHost = $this->host($sourceUrl);

        foreach ($xpath->query('//a[@href]') as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }

            $href = trim((string) $anchor->getAttribute('href'));
            if (!$this->isUsableHref($href)) {
                continue;
            }

            $absolute = $this->resolveUrl($href, $detailPageUrl ?: $sourceUrl);
            if (!$absolute || !preg_match('#^https?://#i', $absolute)) {
                continue;
            }

            $absolute = $this->stripFragment($absolute);
            $key = strtolower(rtrim($absolute, '/'));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $text = $this->compactText((string) $anchor->textContent);
            $context = $this->extractContext($anchor);
            $targetHost = $this->host($absolute);
            $isInternal = $sourceHost !== null && $targetHost === $sourceHost;
            $candidateType = $this->candidateType($absolute, $text, $context, $isInternal, $detailPageUrl !== null);

            if ($candidateType === 'ignore') {
                continue;
            }

            $links[] = [
                'url' => $absolute,
                'text' => mb_substr($text, 0, 255),
                'context' => mb_substr($context, 0, 500),
                'candidate_type' => $candidateType,
                'is_internal' => $isInternal,
                'detail_page_url' => $detailPageUrl,
                'detail_page_title' => $detailPageUrl ? ($title !== '' ? mb_substr($title, 0, 255) : null) : null,
            ];
        }

        return [
            'title' => $title !== '' ? mb_substr($title, 0, 255) : null,
            'links' => $links,
        ];
    }

    private function primaryLinks(array $links): array
    {
        return collect($links)
            ->filter(fn (array $link) => ($link['candidate_type'] ?? '') === 'external_link')
            ->values()
            ->all();
    }

    private function extractFromDetailPages(array $links, string $sourceUrl, int $detailLimit): array
    {
        $warnings = [];
        $detailLinks = collect($links)
            ->filter(fn (array $link) => ($link['candidate_type'] ?? null) === 'directory_detail_candidate')
            ->take($detailLimit)
            ->values();

        $collected = [];
        $fetched = 0;
        foreach ($detailLinks as $detailLink) {
            $detailUrl = (string) ($detailLink['url'] ?? '');
            if ($detailUrl === '') {
                continue;
            }

            $robots = $this->checkRobots($detailUrl);
            if (!$robots['allowed']) {
                $warnings[] = '詳細ページ取得停止（robots.txt）: ' . $detailUrl;
                continue;
            }
            $warnings = array_merge($warnings, $robots['warnings'] ?? []);

            $fetched++;
            $fetchedHtml = $this->fetchHtml($detailUrl);
            if (!$fetchedHtml['ok']) {
                $warnings[] = '詳細ページ取得失敗: ' . $detailUrl . ' / ' . ($fetchedHtml['error'] ?? 'unknown');
                continue;
            }
            $warnings = array_merge($warnings, $fetchedHtml['warnings'] ?? []);

            $parsed = $this->parseLinks($fetchedHtml['html'], $sourceUrl, $detailUrl);
            foreach ($parsed['links'] as $link) {
                if (($link['candidate_type'] ?? '') !== 'external_link') {
                    continue;
                }
                $link['candidate_type'] = 'detail_external_link';
                $link['detail_parent_text'] = $detailLink['text'] ?? null;
                $link['detail_parent_context'] = $detailLink['context'] ?? null;
                $link['detail_page_title'] = $parsed['title'] ?? $link['detail_page_title'] ?? null;
                $collected[] = $link;
            }
        }

        return [
            'links' => $collected,
            'warnings' => array_values(array_unique($warnings)),
            'fetched' => $fetched,
        ];
    }

    private function dedupeAndLimit(array $links, array &$warnings, array &$filterStats): array
    {
        usort($links, function (array $a, array $b) {
            $rank = [
                'detail_external_link' => 0,
                'external_link' => 1,
                'directory_detail_candidate' => 2,
            ];
            return ($rank[$a['candidate_type'] ?? ''] ?? 9) <=> ($rank[$b['candidate_type'] ?? ''] ?? 9);
        });

        $seenUrls = [];
        $seenDomains = [];
        $deduped = [];

        foreach ($links as $link) {
            $url = (string) ($link['url'] ?? '');
            if ($url === '') {
                continue;
            }

            if (!empty($link['is_internal'])) {
                $filterStats['internal_hidden']++;
                continue;
            }

            $urlKey = strtolower(rtrim($url, '/'));
            if (isset($seenUrls[$urlKey])) {
                $filterStats['duplicate_url_hidden']++;
                continue;
            }

            $domainKey = $this->candidateDomainDedupeKey($url);
            if ($domainKey !== null && isset($seenDomains[$domainKey])) {
                $filterStats['duplicate_domain_hidden']++;
                continue;
            }

            $seenUrls[$urlKey] = true;
            if ($domainKey !== null) {
                $seenDomains[$domainKey] = true;
            }

            $deduped[] = $link;
        }

        $limit = (int) config('discovery.directory_link_limit', 200);
        if (count($deduped) > $limit) {
            $warnings[] = "抽出候補が多いため先頭 {$limit} 件に制限した。";
            $deduped = array_slice($deduped, 0, $limit);
        }

        if (($filterStats['duplicate_domain_hidden'] ?? 0) > 0) {
            $warnings[] = '同一候補ドメインの重複を非表示にした: ' . $filterStats['duplicate_domain_hidden'] . '件';
        }

        return $deduped;
    }

    private function candidateDomainDedupeKey(string $url): ?string
    {
        $host = $this->host($url);
        if (!$host) {
            return null;
        }

        if ($this->isSharedProfileHost($host)) {
            $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
            $firstSegment = explode('/', $path)[0] ?? '';

            return $firstSegment !== '' ? $host . '/' . mb_strtolower($firstSegment) : $host;
        }

        return $host;
    }

    private function isSharedProfileHost(string $host): bool
    {
        foreach (array_merge(
            (array) config('discovery.sns_domains', []),
            (array) config('discovery.portal_domains', []),
            (array) config('discovery.map_domains', []),
            (array) config('discovery.ec_domains', [])
        ) as $sharedHost) {
            $sharedHost = preg_replace('/^www\./', '', strtolower((string) $sharedHost));
            if ($sharedHost !== '' && ($host === $sharedHost || str_ends_with($host, '.' . $sharedHost))) {
                return true;
            }
        }

        return false;
    }

    private function candidateType(string $url, string $text, string $context, bool $isInternal, bool $fromDetailPage): string
    {
        if ($this->isUnwantedPath($url) || $this->isWeakNavigationText($text)) {
            return 'ignore';
        }

        if (!$isInternal) {
            return 'external_link';
        }

        if ($fromDetailPage) {
            return 'ignore';
        }

        if ($this->looksLikeDirectoryDetail($url, $text, $context)) {
            return 'directory_detail_candidate';
        }

        return 'ignore';
    }

    private function isUsableHref(string $href): bool
    {
        $lower = strtolower(trim($href));
        return $lower !== ''
            && !str_starts_with($lower, '#')
            && !str_starts_with($lower, 'javascript:')
            && !str_starts_with($lower, 'mailto:')
            && !str_starts_with($lower, 'tel:');
    }

    private function isUnwantedPath(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        if ($path === '') {
            return false;
        }

        foreach ((array) config('discovery.directory_exclude_path_keywords', []) as $needle) {
            if ($needle !== '' && str_contains($path, strtolower((string) $needle))) {
                return true;
            }
        }

        return (bool) preg_match('/\.(jpg|jpeg|png|gif|webp|svg|css|js|ico|zip|doc|docx|xls|xlsx)$/i', $path);
    }

    private function isWeakNavigationText(string $text): bool
    {
        $text = mb_strtolower($this->compactText($text));
        if ($text === '') {
            return false;
        }

        foreach ((array) config('discovery.directory_exclude_text_keywords', []) as $needle) {
            $needle = mb_strtolower((string) $needle);
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return mb_strlen($text) <= 1;
    }

    private function looksLikeDirectoryDetail(string $url, string $text, string $context): bool
    {
        $haystack = mb_strtolower($this->compactText($url . ' ' . $text . ' ' . $context));

        foreach ((array) config('discovery.directory_detail_positive_keywords', []) as $needle) {
            $needle = mb_strtolower((string) $needle);
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function stripFragment(string $url): string
    {
        return preg_replace('/#.*$/', '', $url) ?: $url;
    }

    private function resolveUrl(string $href, string $base): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $baseParts = parse_url($base);
        if (!$baseParts || empty($baseParts['scheme']) || empty($baseParts['host'])) {
            return null;
        }

        $scheme = $baseParts['scheme'];
        $host = $baseParts['host'];
        $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';

        if (str_starts_with($href, '//')) {
            return $scheme . ':' . $href;
        }

        if (str_starts_with($href, '/')) {
            return $scheme . '://' . $host . $port . $href;
        }

        $path = $baseParts['path'] ?? '/';
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        if ($dir === '.' || $dir === '') {
            $dir = '';
        }

        $combined = $dir . '/' . $href;
        $segments = [];
        foreach (explode('/', $combined) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return $scheme . '://' . $host . $port . '/' . implode('/', $segments);
    }

    private function extractContext(DOMElement $anchor): string
    {
        $node = $anchor->parentNode;
        for ($i = 0; $i < 3 && $node; $i++) {
            $text = $this->compactText((string) $node->textContent);
            if (mb_strlen($text) >= 8) {
                return $text;
            }
            $node = $node->parentNode;
        }

        return $this->compactText((string) $anchor->textContent);
    }

    private function compactText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s　]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }
        return preg_replace('/^www\./', '', strtolower($host));
    }
}
