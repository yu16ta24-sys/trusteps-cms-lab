<?php

namespace App\Services\Discovery;

class UrlClassifier
{
    private const SNS_HOSTS = [
        'facebook.com' => 'sns_fb',
        'instagram.com' => 'sns_ig',
        'twitter.com' => 'sns_tw',
        'x.com' => 'sns_tw',
        'line.me' => 'sns_line',
    ];

    private const SAAS_HOSTS = [
        'r.goope.jp',
        'jimdofree.com',
        'jimdosite.com',
        'wixsite.com',
        'wix.com',
        'amebaownd.com',
        'stores.jp',
        'base.shop',
        'thebase.in',
        'wordpress.com',
    ];

    public function classify(string $url, string $baseUrl = '', string $directorySourceUrl = '', string $linkText = ''): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['type' => 'empty', 'confidence' => 0, 'is_business_candidate' => false, 'reason' => 'URLなし'];
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $baseHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $sourceHost = strtolower((string) parse_url($directorySourceUrl, PHP_URL_HOST));

        if ($host === '') {
            return ['type' => 'relative', 'confidence' => 0, 'is_business_candidate' => false, 'reason' => '相対URL'];
        }

        if (str_ends_with($path, '.pdf')) {
            return ['type' => 'pdf', 'confidence' => 0, 'is_business_candidate' => false, 'reason' => 'PDF'];
        }

        foreach (self::SNS_HOSTS as $needle => $type) {
            if ($host === $needle || str_ends_with($host, '.'.$needle)) {
                return ['type' => $type, 'confidence' => 35, 'is_business_candidate' => false, 'reason' => 'SNS'];
            }
        }

        if (str_contains($host, 'google.') || str_contains($host, 'maps.google') || str_contains($host, 'yahoo.co.jp')) {
            return ['type' => 'map_or_portal', 'confidence' => 0, 'is_business_candidate' => false, 'reason' => '地図/ポータル'];
        }

        if (preg_match('/(^|\.)(pref|city|town|village)\./i', $host) || preg_match('/\.(go|lg)\.jp$/i', $host)) {
            return ['type' => 'government', 'confidence' => 0, 'is_business_candidate' => false, 'reason' => '行政ドメイン'];
        }

        if (str_contains($host, 'shokokai.or.jp') || str_contains($host, 'jcci.or.jp') || str_contains($host, 'cci.or.jp')) {
            return ['type' => 'business_group', 'confidence' => 0, 'is_business_candidate' => false, 'reason' => '商工会/会議所系'];
        }

        if ($sourceHost !== '' && $this->isSameSite($url, $directorySourceUrl)) {
            return ['type' => 'internal', 'confidence' => 0, 'is_business_candidate' => false, 'reason' => '名簿元内部リンク'];
        }

        if ($baseHost !== '' && $host === $baseHost && $sourceHost === '') {
            return ['type' => 'internal', 'confidence' => 0, 'is_business_candidate' => false, 'reason' => '同一ページ内リンク'];
        }

        foreach (self::SAAS_HOSTS as $saasHost) {
            if ($host === $saasHost || str_ends_with($host, '.'.$saasHost)) {
                return ['type' => 'own_domain', 'confidence' => 65, 'is_business_candidate' => true, 'reason' => 'SaaS/ビルダー系HP'];
            }
        }

        $confidence = 75;
        if (preg_match('/HP|ホームページ|WEB|Web|公式|サイト/u', $linkText)) {
            $confidence = 90;
        }

        return ['type' => 'own_domain', 'confidence' => $confidence, 'is_business_candidate' => true, 'reason' => '外部独自ドメイン候補'];
    }

    public function isSameSite(string $url, string $sourceUrl): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $sourceHost = strtolower((string) parse_url($sourceUrl, PHP_URL_HOST));
        if ($host === '' || $sourceHost === '') {
            return false;
        }

        if ($host !== $sourceHost) {
            return false;
        }

        // r.goope.jp のような共有ホストは第一パスを商工会/事業者単位として扱う。
        if (in_array($host, ['r.goope.jp', 'jimdofree.com', 'jimdosite.com', 'wixsite.com'], true)) {
            $sourceFirst = $this->firstPathSegment($sourceUrl);
            $urlFirst = $this->firstPathSegment($url);
            if ($sourceFirst !== '' && $urlFirst !== '' && $sourceFirst !== $urlFirst) {
                return false;
            }
        }

        return true;
    }

    public function normalizeAbsoluteUrl(string $href, string $baseUrl): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }
        if (preg_match('/^(javascript|mailto|tel):/i', $href)) {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        $parts = parse_url($baseUrl);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }

        $dir = isset($parts['path']) ? preg_replace('#/[^/]*$#', '/', $parts['path']) : '/';
        return $origin . $dir . $href;
    }

    public function normalizedDomain(?string $url): ?string
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }
        return preg_replace('/^www\./', '', $host);
    }

    private function firstPathSegment(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return '';
        }
        return explode('/', $path)[0] ?? '';
    }
}
