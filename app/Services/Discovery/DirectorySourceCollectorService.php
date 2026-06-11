<?php

namespace App\Services\Discovery;

use App\Models\SourceRecord;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DirectorySourceCollectorService
{
    public function collectMany(array $entryUrls): array
    {
        $allRows = [];
        $allExcluded = [];
        $entryResults = [];
        $seenUrls = [];
        $seenDomains = [];
        $rowId = 0;

        foreach (array_values($entryUrls) as $entryIndex => $entryUrl) {
            $entry = $this->collectFromEntry((string) $entryUrl, $entryIndex);
            $entryResults[] = $entry['entry'];

            foreach ($entry['rows'] as $row) {
                $normalizedKey = (string) ($row['url_key'] ?? $row['url'] ?? '');
                $domainKey = (string) ($row['normalized_domain'] ?? '');

                if ($normalizedKey !== '' && isset($seenUrls[$normalizedKey])) {
                    $row['excluded_reason'] = '同一URLがプレビュー内で重複している。';
                    $allExcluded[] = $row;
                    continue;
                }

                if (!empty($row['dedupe_by_domain']) && $domainKey !== '' && isset($seenDomains[$domainKey])) {
                    $row['excluded_reason'] = '同一ドメインの名簿元候補がプレビュー内で重複している。';
                    $allExcluded[] = $row;
                    continue;
                }

                if ($normalizedKey !== '') {
                    $seenUrls[$normalizedKey] = true;
                }
                if (!empty($row['dedupe_by_domain']) && $domainKey !== '') {
                    $seenDomains[$domainKey] = true;
                }

                $row['row_id'] = $rowId++;
                $allRows[] = $row;
            }

            foreach ($entry['excluded'] as $excluded) {
                $allExcluded[] = $excluded;
            }
        }

        $limit = (int) config('discovery.directory_source_candidate_limit', 200);
        usort($allRows, function (array $a, array $b): int {
            $scoreCompare = ((int) ($b['score'] ?? 0)) <=> ((int) ($a['score'] ?? 0));
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return strcmp((string) ($a['display_name'] ?? $a['url'] ?? ''), (string) ($b['display_name'] ?? $b['url'] ?? ''));
        });

        $keptRows = array_slice($allRows, 0, $limit);
        foreach ($keptRows as $index => &$row) {
            $row['row_id'] = $index;
        }
        unset($row);

        foreach (array_slice($allRows, $limit) as $overflow) {
            $overflow['excluded_reason'] = '候補表示上限を超えたため除外。';
            $allExcluded[] = $overflow;
        }

        return [
            'entry_results' => $entryResults,
            'rows' => $keptRows,
            'excluded' => array_slice($allExcluded, 0, (int) config('discovery.directory_source_excluded_limit', 200)),
        ];
    }

    public function collectFromEntry(string $inputUrl, int $entryIndex = 0): array
    {
        $normalizedUrl = $this->normalizeUrl($inputUrl);
        $entry = [
            'entry_index' => $entryIndex,
            'input_url' => $inputUrl,
            'url' => $normalizedUrl,
            'normalized_domain' => $normalizedUrl ? $this->normalizeHost((string) parse_url($normalizedUrl, PHP_URL_HOST)) : null,
            'ok' => false,
            'error' => null,
            'http_status' => null,
            'title' => null,
            'link_count' => 0,
            'candidate_count' => 0,
            'excluded_count' => 0,
        ];

        if (!$normalizedUrl) {
            $entry['error'] = '入口URLとして解釈できない。';
            return ['entry' => $entry, 'rows' => [], 'excluded' => []];
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('discovery.directory_source_user_agent', 'TRUSTEPS-CMS-Lab-DirectorySourceCollector/0.18.7'),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
                ->timeout((int) config('discovery.directory_source_timeout', 10))
                ->connectTimeout((int) config('discovery.directory_source_connect_timeout', 5))
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
            $entry['error'] = 'HTTP取得に失敗: ' . $e->getMessage();
            return ['entry' => $entry, 'rows' => [], 'excluded' => []];
        }

        $entry['http_status'] = $response->status();
        if (!$response->successful()) {
            $entry['error'] = 'HTTPステータスが正常ではない: ' . $response->status();
            return ['entry' => $entry, 'rows' => [], 'excluded' => []];
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));
        $bodyLimit = (int) config('discovery.directory_source_body_limit', 1200000);
        $html = $this->toUtf8(substr($response->body(), 0, $bodyLimit), $contentType);
        $parsed = $this->extractLinks($html, $normalizedUrl, $entry);

        $entry['ok'] = true;
        $entry['title'] = $parsed['title'];
        $entry['link_count'] = $parsed['link_count'];
        $entry['candidate_count'] = count($parsed['rows']);
        $entry['excluded_count'] = count($parsed['excluded']);

        return [
            'entry' => $entry,
            'rows' => $parsed['rows'],
            'excluded' => $parsed['excluded'],
        ];
    }

    private function extractLinks(string $html, string $entryUrl, array $entry): array
    {
        $rows = [];
        $excluded = [];
        $entryDomain = $entry['normalized_domain'] ?? null;

        if (trim($html) === '') {
            return ['title' => null, 'link_count' => 0, 'rows' => [], 'excluded' => []];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $title = $this->cleanText((string) ($xpath->query('//title')->item(0)?->textContent ?? '')) ?: null;
        $links = $xpath->query('//a[@href]');
        $linkLimit = (int) config('discovery.directory_source_link_scan_limit', 800);
        $linkCount = 0;

        foreach ($links as $link) {
            if (!$link instanceof DOMElement) {
                continue;
            }

            $linkCount++;
            if ($linkCount > $linkLimit) {
                break;
            }

            $href = trim((string) $link->getAttribute('href'));
            $text = $this->cleanText((string) $link->textContent);
            $url = $this->absolutizeUrl($href, $entryUrl);

            if (!$url) {
                $excluded[] = $this->excludedRow($entry, $href, $text, 'http/https以外、またはURLとして解釈できない。');
                continue;
            }

            $url = $this->stripFragment($url);
            $normalizedDomain = $this->normalizeHost((string) parse_url($url, PHP_URL_HOST));
            $path = strtolower((string) parse_url($url, PHP_URL_PATH));
            $aroundText = $this->cleanText($this->aroundText($link));
            $haystack = $this->normalizeSearchText($url . ' ' . $text . ' ' . $aroundText);

            if ($this->isAssetUrl($url)) {
                $excluded[] = $this->excludedRow($entry, $url, $text, '画像・CSS・JS・PDF等のファイルURL。');
                continue;
            }

            if ($this->isHardExcludedPath($path, $haystack)) {
                $excluded[] = $this->excludedRow($entry, $url, $text, '問い合わせ・プライバシー等の名簿元になりにくいリンク。');
                continue;
            }

            if ($this->isKnownNonDirectoryDomain($normalizedDomain)) {
                $excluded[] = $this->excludedRow($entry, $url, $text, 'SNS・地図・大手EC等の名簿元ではない外部サービス。');
                continue;
            }

            $scored = $this->scoreCandidate($url, $text, $aroundText, $normalizedDomain, $entryDomain);
            if (($scored['score'] ?? 0) < (int) config('discovery.directory_source_min_score', 2)) {
                $excluded[] = $this->excludedRow($entry, $url, $text, '名簿元らしさが低いリンク。', $aroundText);
                continue;
            }

            $category = $this->categoryFor($scored['category_key']);
            $role = $this->roleFor($scored['source_role']);
            $duplicateSignals = $this->duplicateSignals($url, $normalizedDomain);
            $confidence = $this->confidenceFor((int) $scored['score'], $duplicateSignals, (string) $scored['source_role']);
            $displayName = $this->displayName($text, $title, $url, $category['label']);

            $rows[] = [
                'entry_url' => $entry['url'],
                'entry_title' => $title,
                'url' => $url,
                'url_key' => $this->normalizeUrlKey($url),
                'normalized_domain' => $normalizedDomain,
                'link_text' => $text,
                'around_text' => $aroundText,
                'display_name' => $displayName,
                'category_key' => $category['key'],
                'category_label' => $category['label'],
                'source_role' => $role['key'],
                'source_role_label' => $role['label'],
                'score' => $scored['score'],
                'reasons' => $scored['reasons'],
                'confidence_label' => $confidence['label'],
                'confidence_reason' => $confidence['reason'],
                'recommendation_label' => $confidence['recommendation_label'],
                'recommendation_reason' => $confidence['recommendation_reason'],
                'duplicate_signals' => $duplicateSignals,
                'default_checked' => $confidence['default_checked'],
                'dedupe_by_domain' => $role['key'] === 'organization_home',
                'same_domain_as_entry' => $normalizedDomain && $entryDomain && $normalizedDomain === $entryDomain,
            ];
        }

        return [
            'title' => $title,
            'link_count' => $linkCount,
            'rows' => $rows,
            'excluded' => $excluded,
        ];
    }

    private function scoreCandidate(string $url, string $text, string $aroundText, ?string $domain, ?string $entryDomain): array
    {
        $score = 0;
        $reasons = [];
        $categoryKey = 'other';
        $role = 'unknown';
        $haystack = $this->normalizeSearchText($url . ' ' . $text . ' ' . $aroundText . ' ' . $domain);

        $categoryRules = [
            'shokokai' => ['商工会', 'shokokai', 'sci.or.jp', '商工会連合会'],
            'chamber' => ['商工会議所', '会議所', 'cci', 'kaigisho'],
            'cooperative' => ['中央会', '協同組合', '事業協同組合', '工業組合', '商業組合', '組合', 'chuokai', 'kumiai'],
            'hygiene' => ['生活衛生', '生衛', 'seiei'],
            'tourism' => ['観光協会', '観光連盟', 'tourism', 'kanko'],
            'public_database' => ['事業所検索', '施設検索', '情報公表', 'オープンデータ', 'データベース', '検索システム', 'go.jp', 'lg.jp'],
            'industry_association' => ['協会', '連盟', '団体', 'association', 'federation'],
        ];

        foreach ($categoryRules as $key => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($haystack, $this->normalizeSearchText($keyword))) {
                    $categoryKey = $key;
                    $score += 3;
                    $reasons[] = "カテゴリ語句: {$keyword}";
                    break 2;
                }
            }
        }

        $directoryKeywords = ['会員一覧', '会員紹介', '事業所一覧', '事業者一覧', '企業一覧', '店舗一覧', 'お店紹介', '加盟店', '部会員', 'member', 'members', 'shoplist', 'business', 'directory', 'search'];
        foreach ($directoryKeywords as $keyword) {
            if (Str::contains($haystack, $this->normalizeSearchText($keyword))) {
                $score += 4;
                $role = 'directory_page';
                $reasons[] = "名簿ページ語句: {$keyword}";
                break;
            }
        }

        $searchKeywords = ['検索', 'webサーチ', 'サーチ', 'search', 'list'];
        foreach ($searchKeywords as $keyword) {
            if (Str::contains($haystack, $this->normalizeSearchText($keyword))) {
                $score += 2;
                if ($role === 'unknown') {
                    $role = 'search_page';
                }
                $reasons[] = "検索ページ語句: {$keyword}";
                break;
            }
        }

        if ($role === 'unknown' && $categoryKey !== 'other') {
            $role = 'organization_home';
            $score += 2;
            $reasons[] = '団体・組織HPとして利用できる可能性。';
        }

        if ($domain && $entryDomain && $domain !== $entryDomain) {
            $score += 1;
            $reasons[] = '入口ページとは別ドメインの外部団体リンク。';
        }

        if ($domain && preg_match('/\.(or\.jp|go\.jp|lg\.jp|ac\.jp)$/i', $domain)) {
            $score += 1;
            $reasons[] = '公的・団体ドメインらしい。';
        }

        if ($text === '' || mb_strlen($text) <= 1) {
            $score -= 1;
            $reasons[] = 'リンクテキストが弱い。';
        }

        return [
            'score' => max(0, $score),
            'category_key' => $categoryKey,
            'source_role' => $role,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    private function categoryFor(string $key): array
    {
        $labels = [
            'shokokai' => '商工会',
            'chamber' => '商工会議所',
            'cooperative' => '中央会・協同組合',
            'industry_association' => '業界団体・協会',
            'hygiene' => '生活衛生組合',
            'tourism' => '観光協会',
            'public_database' => '公的事業所DB',
            'other' => 'その他・要確認',
        ];

        return ['key' => $key, 'label' => $labels[$key] ?? $labels['other']];
    }

    private function roleFor(string $key): array
    {
        $labels = [
            'organization_home' => '団体トップ候補',
            'directory_page' => '名簿ページ候補',
            'search_page' => '検索ページ候補',
            'unknown' => '用途要確認',
        ];

        return ['key' => $key, 'label' => $labels[$key] ?? $labels['unknown']];
    }

    private function confidenceFor(int $score, array $duplicateSignals, string $role): array
    {
        if (!empty($duplicateSignals)) {
            return [
                'label' => '要確認',
                'reason' => '既存DBとの重複シグナルがあるため、自動選択しない。',
                'recommendation_label' => '保存前確認',
                'recommendation_reason' => 'すでに名簿元として保存済みの可能性がある。',
                'default_checked' => false,
            ];
        }

        if ($score >= 8 && in_array($role, ['directory_page', 'search_page', 'organization_home'], true)) {
            return [
                'label' => '高',
                'reason' => '名簿元・団体HPとして使える可能性が高い。',
                'recommendation_label' => '保存推奨',
                'recommendation_reason' => '次工程の名簿ページ自動探索に回す価値が高い。',
                'default_checked' => true,
            ];
        }

        if ($score >= 5) {
            return [
                'label' => '中',
                'reason' => '名簿元候補として使える可能性がある。',
                'recommendation_label' => '必要時のみ',
                'recommendation_reason' => 'カテゴリやリンク先を確認して保存する。',
                'default_checked' => in_array($role, ['directory_page', 'search_page'], true),
            ];
        }

        return [
            'label' => '低',
            'reason' => '名簿元らしさはあるが根拠が弱い。',
            'recommendation_label' => '原則保存しない',
            'recommendation_reason' => '必要な場合だけ手動で選択する。',
            'default_checked' => false,
        ];
    }

    private function duplicateSignals(string $url, ?string $domain): array
    {
        $signals = [];
        $normalizedUrl = $this->normalizeUrlKey($url);

        if ($normalizedUrl !== '') {
            $count = SourceRecord::query()
                ->where('source_type', 'directory_source_candidate')
                ->where(function ($query) use ($url, $normalizedUrl) {
                    $query->where('source_url', $url)
                        ->orWhere('source_url', $normalizedUrl);
                })
                ->count();

            if ($count > 0) {
                $signals[] = "source_url一致 {$count}件";
            }
        }

        if ($domain) {
            $domainCount = SourceRecord::query()
                ->where('source_type', 'directory_source_candidate')
                ->where('normalized_domain', $domain)
                ->count();

            if ($domainCount > 0) {
                $signals[] = "domain一致 {$domainCount}件";
            }
        }

        return $signals;
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (preg_match('/^(mailto:|tel:|javascript:|#)/i', $url)) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
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

    private function absolutizeUrl(string $href, string $baseUrl): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '' || preg_match('/^(mailto:|tel:|javascript:|#)/i', $href)) {
            return null;
        }

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        $base = parse_url($baseUrl);
        if (!$base || empty($base['scheme']) || empty($base['host'])) {
            return null;
        }

        $scheme = $base['scheme'];
        $host = $base['host'];
        $port = isset($base['port']) ? ':' . $base['port'] : '';
        $root = $scheme . '://' . $host . $port;

        if (str_starts_with($href, '/')) {
            return $root . $href;
        }

        $path = (string) ($base['path'] ?? '/');
        $directory = preg_replace('#/[^/]*$#', '/', $path) ?: '/';
        return $this->removeDotSegments($root . $directory . $href);
    }

    private function removeDotSegments(string $url): string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $segments = explode('/', (string) ($parts['path'] ?? ''));
        $output = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($output);
                continue;
            }
            $output[] = $segment;
        }

        $path = '/' . implode('/', $output);
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        return $parts['scheme'] . '://' . $parts['host'] . $port . $path . $query;
    }

    private function stripFragment(string $url): string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        return $parts['scheme'] . '://' . $parts['host'] . $port . $path . $query;
    }

    private function normalizeUrlKey(string $url): string
    {
        $url = strtolower($this->stripFragment($url));
        $url = preg_replace('#^https?://www\.#', 'https://', $url) ?: $url;
        $url = rtrim($url, '/');
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

    private function aroundText(DOMElement $link): string
    {
        $node = $link->parentNode;
        $limit = 0;
        while ($node && $node->parentNode && $limit < 3) {
            if (in_array(strtolower($node->nodeName), ['li', 'tr', 'td', 'article', 'section', 'div'], true)) {
                return (string) $node->textContent;
            }
            $node = $node->parentNode;
            $limit++;
        }

        return (string) $link->textContent;
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;
        return trim($text);
    }

    private function normalizeSearchText(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(['　', '-', '_', '/', '／', '・', '｜', '|'], ' ', $text);
        return preg_replace('/\s+/u', ' ', $text) ?: $text;
    }

    private function isAssetUrl(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        return (bool) preg_match('/\.(?:jpg|jpeg|png|gif|webp|svg|ico|css|js|zip|lzh|doc|docx|xls|xlsx|ppt|pptx|mp4|mp3|pdf)$/i', $path);
    }

    private function isHardExcludedPath(string $path, string $haystack): bool
    {
        $excludePathKeywords = [
            '/privacy', '/policy', '/contact', '/inquiry', '/login', '/logout', '/wp-admin', '/feed', '/rss', '/assets', '/css', '/js', '/images', '/image', '/recruit', '/entry', '/calendar',
        ];

        foreach ($excludePathKeywords as $keyword) {
            if (str_contains($path, $keyword)) {
                return true;
            }
        }

        $excludeTextKeywords = ['お問い合わせ', '問合せ', 'プライバシー', '個人情報保護', 'ログイン', '採用情報', '資料請求'];
        foreach ($excludeTextKeywords as $keyword) {
            if (Str::contains($haystack, $this->normalizeSearchText($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function isKnownNonDirectoryDomain(?string $domain): bool
    {
        if (!$domain) {
            return false;
        }

        $blocked = array_merge(
            (array) config('discovery.sns_domains', []),
            (array) config('discovery.map_domains', []),
            (array) config('discovery.ec_domains', [])
        );

        foreach ($blocked as $blockedDomain) {
            $blockedDomain = strtolower((string) $blockedDomain);
            if ($domain === $blockedDomain || str_ends_with($domain, '.' . $blockedDomain)) {
                return true;
            }
        }

        return false;
    }

    private function excludedRow(array $entry, string $url, string $text, string $reason, string $aroundText = ''): array
    {
        return [
            'entry_url' => $entry['url'] ?? null,
            'url' => $url,
            'link_text' => $text,
            'around_text' => $aroundText,
            'excluded_reason' => $reason,
        ];
    }

    private function displayName(string $text, ?string $entryTitle, string $url, string $fallback): string
    {
        if ($text !== '') {
            return Str::limit($text, 120, '');
        }

        $host = $this->normalizeHost((string) parse_url($url, PHP_URL_HOST));
        if ($host) {
            return $host;
        }

        return $entryTitle ?: $fallback;
    }
}
