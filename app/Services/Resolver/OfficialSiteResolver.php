<?php

namespace App\Services\Resolver;

use App\Models\SourceRecord;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class OfficialSiteResolver
{
    public function analyzeMany(array $urls): array
    {
        $rows = [];

        foreach (array_values($urls) as $index => $url) {
            $row = $this->analyze((string) $url);
            $row['row_id'] = $index;
            $row['line_number'] = $index + 1;
            $rows[] = $row;
        }

        return $rows;
    }

    public function analyze(string $inputUrl): array
    {
        $inputUrl = trim($inputUrl);
        $normalizedUrl = $this->normalizeUrl($inputUrl);

        $base = [
            'input_url' => $inputUrl,
            'normalized_url' => $normalizedUrl,
            'requested_url' => $normalizedUrl,
            'final_url' => $normalizedUrl,
            'normalized_domain' => $normalizedUrl ? $this->normalizeHost((string) parse_url($normalizedUrl, PHP_URL_HOST)) : null,
            'http_status' => null,
            'ok' => false,
            'error' => null,
            'content_type' => null,
            'title' => null,
            'meta_description' => null,
            'meta_generator' => null,
            'canonical_url' => null,
            'og_site_name' => null,
            'ssl_enabled' => $normalizedUrl ? str_starts_with(strtolower($normalizedUrl), 'https://') : false,
            'wordpress_detected' => false,
            'wordpress_signals' => [],
            'cms_guess' => null,
            'builder_guess' => null,
            'has_contact_form' => false,
            'has_public_email' => false,
            'has_phone' => false,
            'emails' => [],
            'phones' => [],
            'warnings' => [],
            'duplicate_signals' => [],
            'confidence_label' => '無効',
            'confidence_reason' => 'URLとして解釈できない。',
            'recommendation_label' => '保存不可',
            'recommendation_reason' => 'URLの形式を確認する必要がある。',
            'default_checked' => false,
        ];

        if (!$normalizedUrl) {
            $base['error'] = 'URLとして解釈できない。';
            return $base;
        }

        $base['duplicate_signals'] = $this->duplicateSignals($normalizedUrl, $base['normalized_domain']);

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('discovery.official_site_resolver_user_agent', 'TRUSTEPS-CMS-Lab-OfficialSiteResolver/0.18.6'),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
                ->timeout((int) config('discovery.official_site_resolver_timeout', 10))
                ->connectTimeout((int) config('discovery.official_site_resolver_connect_timeout', 5))
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'strict' => false,
                        'referer' => true,
                        'track_redirects' => true,
                    ],
                ])
                ->get($normalizedUrl);
        } catch (Throwable $e) {
            $base['error'] = 'HTTP取得に失敗: ' . $e->getMessage();
            $base['confidence_label'] = '要確認';
            $base['confidence_reason'] = 'HTTP取得に失敗したため、手動確認が必要。';
            $base['recommendation_label'] = '保存前確認';
            $base['recommendation_reason'] = 'URL自体は候補として残せるが、現時点ではサイト内容を確認できていない。';
            $base['default_checked'] = false;
            return $base;
        }

        $base['http_status'] = $response->status();
        $base['content_type'] = (string) $response->header('Content-Type', '');
        $base['final_url'] = $this->finalUrlFromRedirectHeaders($response->header('X-Guzzle-Redirect-History'), $normalizedUrl);
        $base['normalized_domain'] = $this->normalizeHost((string) parse_url((string) $base['final_url'], PHP_URL_HOST)) ?: $base['normalized_domain'];
        $base['ssl_enabled'] = str_starts_with(strtolower((string) $base['final_url']), 'https://');
        $base['duplicate_signals'] = $this->duplicateSignals((string) $base['final_url'], $base['normalized_domain']);

        if (!$response->successful()) {
            $base['error'] = 'HTTPステータスが正常ではない: ' . $response->status();
            $base['confidence_label'] = '要確認';
            $base['confidence_reason'] = 'HTTP応答が200番台ではない。';
            $base['recommendation_label'] = '保存前確認';
            $base['recommendation_reason'] = 'URL候補として残す前に、ブラウザで手動確認した方がよい。';
            return $base;
        }

        $body = $this->toUtf8(substr($response->body(), 0, (int) config('discovery.official_site_resolver_body_limit', 1200000)), strtolower($base['content_type'] ?? ''));
        $facts = $this->extractHtmlFacts($body, (string) $base['final_url']);
        $row = array_merge($base, $facts);
        $row['ok'] = true;
        $row = $this->applyQuality($row, $body);

        return $row;
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
        if (!$parts || empty($parts['host']) || empty($parts['scheme'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    private function normalizeHost(string $host): ?string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        $host = preg_replace('/^www\./i', '', $host) ?: $host;
        return $host;
    }

    private function finalUrlFromRedirectHeaders(mixed $redirectHistory, string $fallback): string
    {
        if (is_array($redirectHistory)) {
            $items = array_values(array_filter(array_map(fn ($item) => trim((string) $item), $redirectHistory)));
            return $items ? (string) end($items) : $fallback;
        }

        $redirectHistory = trim((string) ($redirectHistory ?? ''));
        if ($redirectHistory === '') {
            return $fallback;
        }

        $items = array_values(array_filter(array_map('trim', explode(',', $redirectHistory))));
        return $items ? (string) end($items) : $fallback;
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

    private function extractHtmlFacts(string $html, string $finalUrl): array
    {
        $facts = [
            'title' => null,
            'meta_description' => null,
            'meta_generator' => null,
            'canonical_url' => null,
            'og_site_name' => null,
            'has_contact_form' => false,
            'has_public_email' => false,
            'has_phone' => false,
            'emails' => [],
            'phones' => [],
        ];

        if (trim($html) === '') {
            return $facts;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $facts['title'] = $this->cleanText((string) ($xpath->query('//title')->item(0)?->textContent ?? '')) ?: null;
        $facts['meta_description'] = $this->metaContent($xpath, 'description');
        $facts['meta_generator'] = $this->metaContent($xpath, 'generator');
        $facts['og_site_name'] = $this->propertyContent($xpath, 'og:site_name');

        $canonical = $xpath->query('//link[translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="canonical"]')->item(0);
        if ($canonical instanceof DOMElement) {
            $facts['canonical_url'] = $this->absolutizeUrl((string) $canonical->getAttribute('href'), $finalUrl);
        }

        foreach ($xpath->query('//form') as $form) {
            if (!$form instanceof DOMElement) {
                continue;
            }

            $action = strtolower((string) $form->getAttribute('action'));
            $text = strtolower($this->cleanText((string) $form->textContent));
            if (Str::contains($action . ' ' . $text, ['contact', 'inquiry', 'mail', 'お問い合わせ', '問合せ', '送信'])) {
                $facts['has_contact_form'] = true;
                break;
            }
        }

        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/u', $html, $emailMatches);
        $emails = array_values(array_unique(array_map('strtolower', $emailMatches[0] ?? [])));
        $facts['emails'] = array_slice($emails, 0, 5);
        $facts['has_public_email'] = !empty($facts['emails']);

        preg_match_all('/(?:0\d{1,4}[-ー−]?\d{1,4}[-ー−]?\d{3,4})/u', $html, $phoneMatches);
        $phones = array_values(array_unique(array_map(fn ($phone) => str_replace(['ー', '−'], '-', (string) $phone), $phoneMatches[0] ?? [])));
        $facts['phones'] = array_slice($phones, 0, 5);
        $facts['has_phone'] = !empty($facts['phones']);

        return $facts;
    }

    private function metaContent(DOMXPath $xpath, string $name): ?string
    {
        $node = $xpath->query('//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="' . strtolower($name) . '"]')->item(0);
        if (!$node instanceof DOMElement) {
            return null;
        }

        return $this->cleanText((string) $node->getAttribute('content')) ?: null;
    }

    private function propertyContent(DOMXPath $xpath, string $property): ?string
    {
        $node = $xpath->query('//meta[translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="' . strtolower($property) . '"]')->item(0);
        if (!$node instanceof DOMElement) {
            return null;
        }

        return $this->cleanText((string) $node->getAttribute('content')) ?: null;
    }

    private function cleanText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function absolutizeUrl(string $href, string $baseUrl): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        if (str_starts_with($href, '//')) {
            return strtolower((string) $parts['scheme']) . ':' . $href;
        }

        if (str_starts_with($href, '/')) {
            return strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']) . $href;
        }

        return null;
    }

    private function applyQuality(array $row, string $html): array
    {
        $htmlLower = strtolower($html);
        $signals = [];

        if (Str::contains($htmlLower, ['wp-content', 'wp-includes'])) {
            $signals[] = 'wp-content/wp-includes';
        }
        if (!empty($row['meta_generator']) && Str::contains(strtolower((string) $row['meta_generator']), 'wordpress')) {
            $signals[] = 'meta generator WordPress';
        }

        $row['wordpress_signals'] = array_values(array_unique($signals));
        $row['wordpress_detected'] = !empty($row['wordpress_signals']);
        $row['cms_guess'] = $row['wordpress_detected'] ? 'WordPress' : null;
        $row['builder_guess'] = $this->detectBuilder((string) ($row['normalized_domain'] ?? ''), $htmlLower);

        $domain = (string) ($row['normalized_domain'] ?? '');
        $isKnownNonOfficial = $this->isKnownNonOfficialDomain($domain);
        $hasDuplicates = !empty($row['duplicate_signals']);
        $hasTitle = trim((string) ($row['title'] ?? '')) !== '';

        if ($row['ok'] && !$isKnownNonOfficial && $hasTitle && !$hasDuplicates) {
            $row['confidence_label'] = '高';
            $row['confidence_reason'] = 'HTTP取得成功、title取得済み、既存重複なし、SNS/Map/ポータル系ではない。';
            $row['recommendation_label'] = '保存推奨';
            $row['recommendation_reason'] = '公式HP候補としてsource_recordsへ保存してよい。';
            $row['default_checked'] = true;
        } elseif ($row['ok'] && !$isKnownNonOfficial && !$hasDuplicates) {
            $row['confidence_label'] = '中';
            $row['confidence_reason'] = 'HTTP取得成功。ただしtitle等の根拠が弱い。';
            $row['recommendation_label'] = '必要時のみ';
            $row['recommendation_reason'] = '保存前にブラウザ確認すると安全。';
            $row['default_checked'] = false;
        } elseif ($isKnownNonOfficial) {
            $row['confidence_label'] = '低';
            $row['confidence_reason'] = 'SNS/Map/ポータル/EC/簡易ビルダーなど、公式HP本体ではない可能性が高い。';
            $row['recommendation_label'] = '原則保存しない';
            $row['recommendation_reason'] = '補助情報として見る対象。公式HP候補としては低優先。';
            $row['default_checked'] = false;
        } elseif ($hasDuplicates) {
            $row['confidence_label'] = '要確認';
            $row['confidence_reason'] = '既存source_recordsに同一URLまたは同一ドメインがある。';
            $row['recommendation_label'] = '保存前確認';
            $row['recommendation_reason'] = '重複登録になる可能性がある。';
            $row['default_checked'] = false;
        }

        if (!$row['ssl_enabled']) {
            $row['warnings'][] = 'HTTPSではない、または最終URLがHTTP。';
        }
        if ($row['builder_guess']) {
            $row['warnings'][] = $row['builder_guess'] . '系の可能性。';
        }
        if (!$row['title']) {
            $row['warnings'][] = 'titleを取得できなかった。';
        }

        $row['warnings'] = array_values(array_unique($row['warnings'] ?? []));
        return $row;
    }

    private function detectBuilder(string $domain, string $htmlLower): ?string
    {
        $checks = [
            'Wix' => ['wixsite.com', 'wixstatic.com', 'wix.com'],
            'Jimdo' => ['jimdo.com', 'jimdosite.com', 'jimdofree.com'],
            'ペライチ' => ['peraichi.com'],
            'STUDIO' => ['studio.site', 'studio.design'],
            'Ameba Ownd' => ['amebaownd.com', 'ownd.site'],
            'Shopify' => ['myshopify.com', 'cdn.shopify.com'],
        ];

        foreach ($checks as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($domain, $needle) || str_contains($htmlLower, $needle)) {
                    return $label;
                }
            }
        }

        return null;
    }

    private function isKnownNonOfficialDomain(string $domain): bool
    {
        foreach (['sns_domains', 'map_domains', 'portal_domains', 'ec_domains'] as $key) {
            foreach ((array) config('discovery.' . $key, []) as $known) {
                $known = strtolower((string) $known);
                if ($domain === $known || str_ends_with($domain, '.' . $known)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function duplicateSignals(string $url, ?string $domain): array
    {
        $signals = [];
        $urlCount = SourceRecord::query()->where('source_url', $url)->count();
        if ($urlCount > 0) {
            $signals[] = "source_url一致 {$urlCount}件";
        }

        if ($domain) {
            $domainCount = SourceRecord::query()->where('normalized_domain', $domain)->count();
            if ($domainCount > 0) {
                $signals[] = "domain一致 {$domainCount}件";
            }
        }

        return $signals;
    }
}
