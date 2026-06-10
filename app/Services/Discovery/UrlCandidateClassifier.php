<?php

namespace App\Services\Discovery;

use Illuminate\Support\Str;

class UrlCandidateClassifier
{
    public function classify(string $input): array
    {
        $raw = trim($input);
        $warnings = [];

        if ($raw === '') {
            return $this->emptyResult($input, ['空行のためスキップ候補。']);
        }

        $candidate = $this->extractUrlCandidate($raw);

        if ($candidate === '') {
            return $this->emptyResult($input, ['URL候補を読み取れなかった。']);
        }

        $normalizedUrl = $this->normalizeUrl($candidate);
        $normalizedDomain = $this->normalizeDomain($normalizedUrl ?? $candidate);

        if (!$normalizedUrl || !$normalizedDomain) {
            return $this->emptyResult($input, ['URLとして解釈できなかった。']);
        }

        $path = (string) parse_url($normalizedUrl, PHP_URL_PATH);
        $classification = 'official_site_candidate';
        $label = '公式候補';
        $color = 'green';
        $confidence = 0.70;

        if ($this->isPdfUrl($path)) {
            $classification = 'pdf_candidate';
            $label = 'PDF';
            $color = 'gray';
            $confidence = 0.20;
            $warnings[] = 'PDF URL。公式HP本体ではない可能性が高い。';
        } elseif ($this->isMapDomain($normalizedDomain, $path)) {
            $classification = 'map_candidate';
            $label = 'Map';
            $color = 'red';
            $confidence = 0.05;
            $warnings[] = 'Googleマップ等の地図URL。公式HPとして扱わない。';
        } elseif ($this->matchesConfiguredDomain($normalizedDomain, 'discovery.portal_domains')) {
            $classification = 'portal_candidate';
            $label = 'ポータル';
            $color = 'amber';
            $confidence = 0.10;
            $warnings[] = 'ポータル系ドメイン。公式HPとして扱わない。';
        } elseif ($this->matchesConfiguredDomain($normalizedDomain, 'discovery.sns_domains')) {
            $classification = 'sns_candidate';
            $label = 'SNS';
            $color = 'blue';
            $confidence = 0.10;
            $warnings[] = 'SNS系URL。公式HP候補としては低優先。';
        } elseif ($this->matchesConfiguredDomain($normalizedDomain, 'discovery.builder_domains')) {
            $classification = 'builder_site_candidate';
            $label = 'ビルダー';
            $color = 'purple';
            $confidence = 0.55;
            $warnings[] = 'Wix/Jimdo/ペライチ等の簡易ビルダー候補。除外せず観測対象として残す。';
        } elseif ($this->matchesConfiguredDomain($normalizedDomain, 'discovery.ec_domains')) {
            $classification = 'ec_candidate';
            $label = 'EC';
            $color = 'teal';
            $confidence = 0.25;
            $warnings[] = 'EC/モール系URL。公式HP本体ではない可能性がある。';
        } elseif ($this->looksLikeSharedOrShortUrl($normalizedDomain)) {
            $classification = 'unknown';
            $label = '不明';
            $color = 'gray';
            $confidence = 0.20;
            $warnings[] = '短縮URLまたは共有ドメインの可能性。手動確認が必要。';
        }

        return [
            'input_line' => $raw,
            'raw_url' => $candidate,
            'normalized_url' => $normalizedUrl,
            'normalized_domain' => $normalizedDomain,
            'classification' => $classification,
            'classification_label' => $label,
            'badge_color' => $color,
            'confidence' => $confidence,
            'warnings' => array_values(array_unique($warnings)),
            'is_valid_url' => true,
        ];
    }

    public function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $url = trim($url, " \t\n\r\0\x0B<>\"'、,，");
        $url = preg_replace('/#.*$/', '', $url) ?: $url;

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $path . $query;
    }

    public function normalizeDomain(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host);

        return $host ?: null;
    }

    private function extractUrlCandidate(string $line): string
    {
        if (preg_match('#https?://[^\s<>"]+#iu', $line, $matches)) {
            return trim($matches[0]);
        }

        $line = trim($line);
        $line = preg_replace('/^[\-\*・●○\d\.\)\s]+/u', '', $line) ?: $line;

        if (preg_match('/([a-z0-9][a-z0-9\-\.]+\.[a-z]{2,}(?:\/[^\s]*)?)/iu', $line, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function emptyResult(string $input, array $warnings): array
    {
        return [
            'input_line' => trim($input),
            'raw_url' => null,
            'normalized_url' => null,
            'normalized_domain' => null,
            'classification' => 'unknown',
            'classification_label' => '不明',
            'badge_color' => 'gray',
            'confidence' => 0,
            'warnings' => $warnings,
            'is_valid_url' => false,
        ];
    }

    private function isPdfUrl(string $path): bool
    {
        return Str::endsWith(Str::lower($path), '.pdf');
    }

    private function isMapDomain(string $domain, string $path): bool
    {
        if ($domain === 'maps.app.goo.gl' || $domain === 'goo.gl') {
            return true;
        }

        if ($domain === 'maps.google.com') {
            return true;
        }

        if (in_array($domain, ['google.com', 'google.co.jp'], true) && Str::startsWith($path, '/maps')) {
            return true;
        }

        return false;
    }

    private function matchesConfiguredDomain(string $domain, string $configKey): bool
    {
        foreach ((array) config($configKey, []) as $needle) {
            $needle = strtolower((string) $needle);
            if ($needle === '') {
                continue;
            }

            if ($domain === $needle || Str::endsWith($domain, '.' . $needle)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeSharedOrShortUrl(string $domain): bool
    {
        return in_array($domain, [
            'bit.ly',
            'tinyurl.com',
            'x.gd',
            'qr.paps.jp',
        ], true);
    }
}
