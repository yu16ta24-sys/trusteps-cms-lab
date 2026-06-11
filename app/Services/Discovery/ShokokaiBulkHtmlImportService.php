<?php

namespace App\Services\Discovery;

use App\Models\SourceRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ShokokaiBulkHtmlImportService
{
    /**
     * @return array<string,mixed>
     */
    public function preview(string $html): array
    {
        $rows = $this->parseRows($html);
        $rows = $this->applyDuplicateSignals($rows);

        $summary = $this->buildSummary($rows);
        $prefGroups = $this->buildPrefGroups($rows);

        return [
            'rows' => $rows,
            'summary' => $summary,
            'pref_groups' => $prefGroups,
            'meta' => [
                'collector_version' => '0.18.9',
                'collector_type' => 'shokokai_web_search_bulk_html',
                'source_name' => '全国商工会WEBサーチ 全件HTML',
                'created_at' => now()->timestamp,
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function parseRows(string $html): array
    {
        $html = $this->normalizeEncoding($html);

        preg_match_all('/<li\b[^>]*>(.*?)<\/li>/isu', $html, $matches);
        $items = $matches[1] ?? [];

        $rows = [];
        $seenUrls = [];
        $seenDomains = [];
        $seenCodes = [];
        $rowId = 1;

        foreach ($items as $index => $itemHtml) {
            $row = $this->parseItem($itemHtml, $index + 1, $rowId);

            $withinDuplicateSignals = [];
            if (!empty($row['url'])) {
                $urlKey = mb_strtolower((string) $row['url']);
                if (isset($seenUrls[$urlKey])) {
                    $withinDuplicateSignals[] = '貼り付けHTML内でURL重複';
                }
                $seenUrls[$urlKey] = true;
            }

            if (!empty($row['normalized_domain'])) {
                $domainKey = (string) $row['normalized_domain'];
                if (isset($seenDomains[$domainKey])) {
                    $withinDuplicateSignals[] = '貼り付けHTML内でドメイン重複';
                }
                $seenDomains[$domainKey] = true;
            }

            $codeKey = trim((string) ($row['pref_code'] ?? '')) . ':' . trim((string) ($row['shokokai_code'] ?? ''));
            if ($codeKey !== ':' && isset($seenCodes[$codeKey])) {
                $withinDuplicateSignals[] = '貼り付けHTML内で商工会コード重複';
            }
            if ($codeKey !== ':') {
                $seenCodes[$codeKey] = true;
            }

            $row['duplicate_signals'] = array_values(array_unique(array_merge($row['duplicate_signals'], $withinDuplicateSignals)));
            $rows[] = $row;
            $rowId++;
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    private function parseItem(string $itemHtml, int $rawIndex, int $rowId): array
    {
        $mapInfo = $this->extractMapGo($itemHtml);
        $href = $this->extractFirstHref($itemHtml);
        $urlInfo = $this->normalizeUrl($href);
        $text = $this->plainText($itemHtml);

        $organizationName = $this->extractAlt($itemHtml)
            ?: ($mapInfo['organization_name'] ?? null)
            ?: $this->extractFirstAnchorText($itemHtml)
            ?: $this->extractNameFromText($text)
            ?: '名称未取得';

        $prefCode = $mapInfo['pref_code'] ?? $this->guessPrefCodeByAddress($mapInfo['address'] ?? null);
        $prefLabel = $this->prefLabel($prefCode, $mapInfo['address'] ?? null);
        $organizationType = $this->classifyOrganizationType($organizationName, $prefCode, $mapInfo['shokokai_code'] ?? null);

        $postalCode = $this->extractPostalCode($text);
        $address = $mapInfo['address'] ?? $this->extractAddressFromText($text);
        $tel = $this->extractTelOrFax($text, 'TEL');
        $fax = $this->extractTelOrFax($text, 'FAX');

        $statusKey = $this->statusKey($href, $urlInfo);
        $statusLabel = $this->statusLabel($statusKey);
        $categoryLabel = $this->categoryLabel($organizationType, $urlInfo['domain'] ?? null);
        $storable = $statusKey === 'valid_url';
        $defaultChecked = $storable;

        $duplicateSignals = [];
        if ($statusKey === 'no_url') {
            $duplicateSignals[] = 'URLなし';
        }
        if ($statusKey === 'invalid_url') {
            $duplicateSignals[] = 'URL要確認';
        }

        $confidence = $this->confidenceFor($statusKey, $organizationType, $urlInfo['domain'] ?? null);

        return [
            'row_id' => $rowId,
            'raw_index' => $rawIndex,
            'organization_name' => $organizationName,
            'organization_type' => $organizationType,
            'organization_type_label' => $this->organizationTypeLabel($organizationType),
            'category_label' => $categoryLabel,
            'pref_code' => $prefCode,
            'pref_label' => $prefLabel,
            'shokokai_code' => $mapInfo['shokokai_code'] ?? null,
            'postal_code' => $postalCode,
            'address' => $address,
            'tel' => $tel,
            'fax' => $fax,
            'url' => $urlInfo['url'] ?? null,
            'raw_url' => $href,
            'normalized_domain' => $urlInfo['domain'] ?? null,
            'status_key' => $statusKey,
            'status_label' => $statusLabel,
            'storable' => $storable,
            'default_checked' => $defaultChecked,
            'duplicate_signals' => $duplicateSignals,
            'confidence_label' => $confidence['label'],
            'confidence_reason' => $confidence['reason'],
            'recommendation_label' => $storable ? '保存推奨' : '保存不可',
            'recommendation_reason' => $storable
                ? '全国商工会WEBサーチのHTMLから抽出した有効URL。名簿元候補として保存可能。'
                : 'URLが無い、またはURL形式が壊れているため自動保存しない。',
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function applyDuplicateSignals(array $rows): array
    {
        $urls = collect($rows)
            ->pluck('url')
            ->filter()
            ->unique()
            ->values();

        $domains = collect($rows)
            ->pluck('normalized_domain')
            ->filter()
            ->unique()
            ->values();

        $existingUrls = $urls->isEmpty()
            ? collect()
            : SourceRecord::query()
                ->whereIn('source_url', $urls->all())
                ->pluck('source_url')
                ->filter()
                ->map(fn ($url) => mb_strtolower((string) $url))
                ->flip();

        $existingDomains = $domains->isEmpty()
            ? collect()
            : SourceRecord::query()
                ->whereIn('normalized_domain', $domains->all())
                ->pluck('normalized_domain')
                ->filter()
                ->map(fn ($domain) => mb_strtolower((string) $domain))
                ->flip();

        foreach ($rows as &$row) {
            $signals = $row['duplicate_signals'] ?? [];
            $urlKey = mb_strtolower((string) ($row['url'] ?? ''));
            $domainKey = mb_strtolower((string) ($row['normalized_domain'] ?? ''));

            if ($urlKey !== '' && $existingUrls->has($urlKey)) {
                $signals[] = '既存source_recordsにURL登録済み';
            }
            if ($domainKey !== '' && $existingDomains->has($domainKey)) {
                $signals[] = '既存source_recordsにドメイン登録済み';
            }

            $row['duplicate_signals'] = array_values(array_unique($signals));
            if (!empty($signals)) {
                $row['default_checked'] = false;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private function buildSummary(array $rows): array
    {
        return [
            'total' => count($rows),
            'valid_url' => collect($rows)->where('status_key', 'valid_url')->count(),
            'no_url' => collect($rows)->where('status_key', 'no_url')->count(),
            'invalid_url' => collect($rows)->where('status_key', 'invalid_url')->count(),
            'duplicate' => collect($rows)->filter(fn ($row) => !empty($row['duplicate_signals'] ?? []))->count(),
            'default_checked' => collect($rows)->where('default_checked', true)->count(),
            'pref_count' => collect($rows)->pluck('pref_code')->filter()->unique()->count(),
            'local_shokokai' => collect($rows)->where('organization_type', 'local_shokokai')->count(),
            'pref_federation' => collect($rows)->where('organization_type', 'prefectural_federation')->count(),
            'national_federation' => collect($rows)->where('organization_type', 'national_federation')->count(),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function buildPrefGroups(array $rows): array
    {
        return collect($rows)
            ->groupBy(fn ($row) => sprintf('%02d_%s', (int) ($row['pref_code'] ?: 99), $row['pref_label'] ?? '不明'))
            ->map(function (Collection $prefRows) {
                $first = $prefRows->first();
                return [
                    'pref_code' => $first['pref_code'] ?? null,
                    'pref_label' => $first['pref_label'] ?? '不明',
                    'summary' => [
                        'total' => $prefRows->count(),
                        'valid_url' => $prefRows->where('status_key', 'valid_url')->count(),
                        'no_url' => $prefRows->where('status_key', 'no_url')->count(),
                        'invalid_url' => $prefRows->where('status_key', 'invalid_url')->count(),
                        'duplicate' => $prefRows->filter(fn ($row) => !empty($row['duplicate_signals'] ?? []))->count(),
                    ],
                    'rows' => $prefRows->values()->all(),
                ];
            })
            ->sortBy(fn ($group) => (string) ($group['pref_code'] ?? '99'))
            ->values()
            ->all();
    }

    private function normalizeEncoding(string $html): string
    {
        $encoding = mb_detect_encoding($html, ['UTF-8', 'SJIS-win', 'EUC-JP', 'ISO-2022-JP', 'ASCII'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            return mb_convert_encoding($html, 'UTF-8', $encoding);
        }

        return $html;
    }

    /**
     * @return array<string,string|null>
     */
    private function extractMapGo(string $html): array
    {
        if (!preg_match("/mapGo\(\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*\)/u", $html, $m)) {
            return [];
        }

        return [
            'pref_code' => $m[1] !== '' ? $m[1] : null,
            'shokokai_code' => $m[2] !== '' ? $m[2] : null,
            'map_type' => $m[3] !== '' ? $m[3] : null,
            'organization_name' => $this->decode($m[4]),
            'address' => $this->decode($m[5]),
        ];
    }

    private function extractAlt(string $html): ?string
    {
        if (preg_match('/<img\b[^>]*\balt=["\']([^"\']+)["\'][^>]*>/isu', $html, $m)) {
            return $this->cleanText($this->decode($m[1]));
        }

        return null;
    }

    private function extractFirstHref(string $html): ?string
    {
        if (preg_match('/<a\b[^>]*\bhref=["\']([^"\']+)["\'][^>]*>/isu', $html, $m)) {
            return trim($this->decode($m[1]));
        }

        return null;
    }

    private function extractFirstAnchorText(string $html): ?string
    {
        if (preg_match('/<a\b[^>]*>(.*?)<\/a>/isu', $html, $m)) {
            return $this->cleanText($this->decode(strip_tags($m[1])));
        }

        return null;
    }

    private function extractNameFromText(string $text): ?string
    {
        if (preg_match('/^\s*(?:\d+\.)?\s*([^\r\n]+?商工会(?:連合会)?)/u', $text, $m)) {
            return $this->cleanText($m[1]);
        }

        return null;
    }

    private function extractPostalCode(string $text): ?string
    {
        if (preg_match('/〒\s*([0-9]{3}-?[0-9]{4})/u', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    private function extractAddressFromText(string $text): ?string
    {
        if (preg_match('/住所\s*([^\r\n]+?)(?:\s*TEL|\s*FAX|$)/u', $text, $m)) {
            return $this->cleanText($m[1]);
        }

        return null;
    }

    private function extractTelOrFax(string $text, string $label): ?string
    {
        if (preg_match('/' . preg_quote($label, '/') . '\s*([0-9０-９\-ー−]+(?:\s*[0-9０-９\-ー−]+)?)/u', $text, $m)) {
            return $this->normalizePhone($m[1]);
        }

        return null;
    }

    /**
     * @return array{url:?string,domain:?string,valid:bool,reason:?string}
     */
    private function normalizeUrl(?string $url): array
    {
        if ($url === null || trim($url) === '') {
            return ['url' => null, 'domain' => null, 'valid' => false, 'reason' => 'URLなし'];
        }

        $url = trim($this->decode($url));
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        if (!preg_match('/^https?:\/\//i', $url)) {
            return ['url' => $url, 'domain' => null, 'valid' => false, 'reason' => 'http/httpsではない'];
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./i', '', $host) ?: '';

        if ($host === '' || str_starts_with($host, '.') || str_ends_with($host, '.') || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['url' => $url, 'domain' => $host ?: null, 'valid' => false, 'reason' => 'URL形式が不正'];
        }

        return ['url' => $url, 'domain' => $host, 'valid' => true, 'reason' => null];
    }

    /** @param array{url:?string,domain:?string,valid:bool,reason:?string} $urlInfo */
    private function statusKey(?string $rawUrl, array $urlInfo): string
    {
        if ($rawUrl === null || trim($rawUrl) === '') {
            return 'no_url';
        }

        return $urlInfo['valid'] ? 'valid_url' : 'invalid_url';
    }

    private function statusLabel(string $statusKey): string
    {
        return match ($statusKey) {
            'valid_url' => '有効URL',
            'no_url' => 'URLなし',
            'invalid_url' => 'URL要確認',
            default => '要確認',
        };
    }

    private function classifyOrganizationType(string $name, ?string $prefCode, ?string $shokokaiCode): string
    {
        if ($prefCode === '00' || str_contains($name, '全国商工会連合会')) {
            return 'national_federation';
        }

        if ($shokokaiCode === '0021' || preg_match('/(都|道|府|県)商工会連合会/u', $name)) {
            return 'prefectural_federation';
        }

        return 'local_shokokai';
    }

    private function organizationTypeLabel(string $type): string
    {
        return match ($type) {
            'national_federation' => '全国連合会',
            'prefectural_federation' => '都道府県連合会',
            'local_shokokai' => '地域商工会',
            default => 'その他',
        };
    }

    private function categoryLabel(string $organizationType, ?string $domain): string
    {
        $label = $this->organizationTypeLabel($organizationType);
        if ($domain && str_contains($domain, 'shokokai.or.jp')) {
            return $label . ' / 商工会共通系';
        }
        if ($domain && str_contains($domain, 'goope.jp')) {
            return $label . ' / グーペ系';
        }

        return $label;
    }

    /** @return array{label:string,reason:string} */
    private function confidenceFor(string $statusKey, string $organizationType, ?string $domain): array
    {
        if ($statusKey !== 'valid_url') {
            return ['label' => '無効', 'reason' => '有効なURLが無いため自動保存対象外。'];
        }

        if ($organizationType === 'local_shokokai' && $domain && !str_contains($domain, 'shokokai.or.jp')) {
            return ['label' => '高', 'reason' => '地域商工会の有効URL。商工会HP起点として利用しやすい。'];
        }

        return ['label' => '中', 'reason' => '有効URLだが連合会または共通CMS系の可能性があるため確認推奨。'];
    }

    private function guessPrefCodeByAddress(?string $address): ?string
    {
        if (!$address) {
            return null;
        }

        foreach ($this->prefectures() as $code => $label) {
            if ($code !== '00' && str_starts_with($address, $label)) {
                return $code;
            }
        }

        return null;
    }

    private function prefLabel(?string $prefCode, ?string $address): string
    {
        if ($prefCode && isset($this->prefectures()[$prefCode])) {
            return $this->prefectures()[$prefCode];
        }

        $guessed = $this->guessPrefCodeByAddress($address);
        if ($guessed && isset($this->prefectures()[$guessed])) {
            return $this->prefectures()[$guessed];
        }

        return '不明';
    }

    /** @return array<string,string> */
    private function prefectures(): array
    {
        return ['00' => '全国'] + config('discovery.shokokai_web_search_prefectures', []);
    }

    private function plainText(string $html): string
    {
        $html = preg_replace('/<br\s*\/?>/iu', "\n", $html) ?? $html;
        $text = $this->decode(strip_tags($html));
        $text = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\R+/u', "\n", $text) ?? $text;

        return trim($text);
    }

    private function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = $this->decode($value);
        $value = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function normalizePhone(string $phone): string
    {
        $phone = mb_convert_kana($phone, 'n', 'UTF-8');
        $phone = str_replace(['ー', '−', '－'], '-', $phone);
        $phone = preg_replace('/\s+/u', '', $phone) ?? $phone;

        return $phone;
    }
}
