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
    public function extract(string $url): array
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

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('discovery.directory_user_agent', 'TRUSTEPS-CMS-Lab-DiscoveryBot/0.18.2'),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
                ->timeout((int) config('discovery.directory_timeout', 10))
                ->connectTimeout((int) config('discovery.directory_connect_timeout', 5))
                ->get($sourceUrl);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => '名簿URLへのHTTP取得に失敗した: ' . $e->getMessage(),
                'warnings' => $warnings,
            ];
        }

        if (!$response->successful()) {
            return [
                'ok' => false,
                'error' => '名簿URLのHTTPステータスが正常ではない: ' . $response->status(),
                'warnings' => $warnings,
            ];
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));
        if ($contentType !== '' && !str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml')) {
            $warnings[] = 'Content-TypeがHTMLではない可能性: ' . $contentType;
        }

        $html = $this->toUtf8($response->body(), $contentType);
        $parsed = $this->parseLinks($html, $sourceUrl);
        $limit = (int) config('discovery.directory_link_limit', 200);
        $links = array_slice($parsed['links'], 0, $limit);

        if (count($parsed['links']) > $limit) {
            $warnings[] = "抽出リンクが多いため先頭 {$limit} 件に制限した。";
        }

        if (empty($links)) {
            $warnings[] = 'aタグからHTTP/HTTPSリンクを抽出できなかった。JS描画ページ、PDF、またはリンクがない名簿の可能性。';
        }

        return [
            'ok' => true,
            'source_url' => $sourceUrl,
            'title' => $parsed['title'],
            'links' => $links,
            'warnings' => array_values(array_unique($warnings)),
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
                'User-Agent' => (string) config('discovery.directory_user_agent', 'TRUSTEPS-CMS-Lab-DiscoveryBot/0.18.2'),
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

    private function parseLinks(string $html, string $sourceUrl): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $title = trim((string) optional($xpath->query('//title')->item(0))->textContent);
        $links = [];
        $seen = [];

        foreach ($xpath->query('//a[@href]') as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }

            $href = trim((string) $anchor->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:') || str_starts_with(strtolower($href), 'mailto:') || str_starts_with(strtolower($href), 'tel:')) {
                continue;
            }

            $absolute = $this->resolveUrl($href, $sourceUrl);
            if (!$absolute || !preg_match('#^https?://#i', $absolute)) {
                continue;
            }

            $key = strtolower(rtrim($absolute, '/'));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $text = $this->compactText((string) $anchor->textContent);
            $context = $this->extractContext($anchor);

            $links[] = [
                'url' => $absolute,
                'text' => mb_substr($text, 0, 255),
                'context' => mb_substr($context, 0, 500),
            ];
        }

        return [
            'title' => $title !== '' ? mb_substr($title, 0, 255) : null,
            'links' => $links,
        ];
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
        for ($i = 0; $i < 2 && $node; $i++) {
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
}
