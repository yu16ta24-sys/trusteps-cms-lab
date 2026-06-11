<?php

namespace App\Services\Discovery;

use App\Models\SourceRecord;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class ShokokaiWebSearchService
{
    public function search(string $prefCode, int $kensu, int $maxPages, string $keyword = ''): array
    {
        $prefectures = config('discovery.shokokai_web_search_prefectures', []);
        $prefLabel = (string) ($prefectures[$prefCode] ?? $prefCode);
        $endpoint = (string) config('discovery.shokokai_web_search_endpoint');
        $hardPageLimit = (int) config('discovery.shokokai_web_search_page_hard_limit', 20);
        $resultLimit = (int) config('discovery.shokokai_web_search_result_limit', 500);
        $maxPages = max(1, min($maxPages, $hardPageLimit));
        $kensu = in_array($kensu, [10, 50, 100], true) ? $kensu : 50;

        $rows = [];
        $excluded = [];
        $pageResults = [];
        $seenKeys = [];
        $seenPayloads = [];
        $payload = null;
        $rowId = 0;
        $stopReason = null;

        for ($page = 1; $page <= $maxPages; $page++) {
            if ($page === 1) {
                $fetched = $this->fetchFirstSearchHtml($prefCode, $prefLabel, $kensu, $keyword);
                $payload = $fetched['payload'] ?? $this->initialPayload($prefCode, $kensu, $keyword, '0', 'Plus');
            } else {
                if (!is_array($payload)) {
                    $stopReason = '次ページ用ペイロードなし。';
                    break;
                }

                $payloadSignature = md5(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                if (isset($seenPayloads[$payloadSignature])) {
                    $stopReason = '同じ検索ペイロードが再登場したため停止。';
                    break;
                }
                $seenPayloads[$payloadSignature] = true;

                $fetched = $this->postHtml($endpoint, $payload, 'https://www12.shokokai.or.jp/hpsearch/top/php/search.php');
                $fetched['page'] = $page;
                $fetched['attempt_label'] = 'pagination';
                $fetched['attempts'] = [];
            }

            $pageResult = [
                'page' => $page,
                'ok' => false,
                'http_status' => $fetched['http_status'] ?? null,
                'payload' => $payload,
                'attempt_label' => $fetched['attempt_label'] ?? null,
                'attempts' => $fetched['attempts'] ?? [],
                'row_count' => 0,
                'new_row_count' => 0,
                'excluded_count' => 0,
                'error' => $fetched['error'] ?? null,
            ];

            if (empty($fetched['ok']) || !is_string($fetched['html'] ?? null)) {
                $pageResults[] = $pageResult;
                $stopReason = $fetched['stop_reason'] ?? '検索ページ取得失敗';
                break;
            }

            $html = (string) $fetched['html'];
            $parsed = $this->parseSearchResult($html, $prefCode, $prefLabel, $page);
            $pageRows = $parsed['rows'];
            $pageExcluded = $parsed['excluded'];
            $newRows = 0;

            foreach ($pageRows as $row) {
                $key = (string) ($row['url_key'] ?: ('name:' . ($row['organization_name'] ?? '') . ':' . ($row['address'] ?? '')));
                if ($key !== '' && isset($seenKeys[$key])) {
                    $row['excluded_reason'] = '検索結果内で同一候補が重複している。';
                    $excluded[] = $row;
                    continue;
                }

                if ($key !== '') {
                    $seenKeys[$key] = true;
                }

                $row['row_id'] = $rowId++;
                $rows[] = $row;
                $newRows++;

                if (count($rows) >= $resultLimit) {
                    $stopReason = '表示上限に到達。';
                    break 2;
                }
            }

            foreach ($pageExcluded as $item) {
                $excluded[] = $item;
            }

            $pageResult['ok'] = true;
            $pageResult['row_count'] = count($pageRows);
            $pageResult['new_row_count'] = $newRows;
            $pageResult['excluded_count'] = count($pageExcluded);
            $pageResults[] = $pageResult;

            $nextPayload = $this->extractNextPayload($html, $prefCode, $kensu, $keyword);
            if (!$nextPayload) {
                $stopReason = count($rows) > 0
                    ? '最終ページ。次ページフォームなし。'
                    : '検索結果0件。POST条件が公式側フォームと合っていない、または該当なし。取得ページログを確認して。';
                break;
            }

            if ($page > 1 && $newRows === 0) {
                $stopReason = '新規候補が増えなかったため停止。';
                break;
            }

            $payload = $nextPayload;
        }

        return [
            'rows' => $rows,
            'excluded' => array_slice($excluded, 0, 300),
            'page_results' => $pageResults,
            'summary' => [
                'pref_code' => $prefCode,
                'pref_label' => $prefLabel,
                'kensu' => $kensu,
                'max_pages' => $maxPages,
                'keyword' => $keyword,
                'total' => count($rows),
                'default_checked' => collect($rows)->filter(fn ($row) => !empty($row['default_checked']))->count(),
                'invalid' => collect($rows)->filter(fn ($row) => empty($row['storable']))->count(),
                'excluded' => count($excluded),
                'page_count' => count($pageResults),
                'stop_reason' => $stopReason,
            ],
        ];
    }

    private function fetchFirstSearchHtml(string $prefCode, string $prefLabel, int $kensu, string $keyword): array
    {
        $endpoint = (string) config('discovery.shokokai_web_search_endpoint');
        $attempts = [];
        $lastFetched = null;

        foreach ($this->initialDirectPayloads($prefCode, $kensu, $keyword) as $label => $payload) {
            $fetched = $this->postHtml($endpoint, $payload, 'https://www.shokokai.or.jp/?page_id=1754');
            $attempt = $this->summarizeAttempt($label, $fetched, $prefCode, $prefLabel);
            $attempts[] = $attempt;
            $lastFetched = $fetched;

            if (!empty($attempt['row_count'])) {
                $fetched['attempt_label'] = $label;
                $fetched['attempts'] = $attempts;
                $fetched['payload'] = $payload;
                return $fetched;
            }
        }

        $conditionFetched = $this->postConditionAndSearch($prefCode, $prefLabel, $kensu, $keyword, $attempts);
        if (is_array($conditionFetched) && !empty($conditionFetched['ok'])) {
            return $conditionFetched;
        }

        if (is_array($conditionFetched)) {
            $lastFetched = $conditionFetched;
        }

        return [
            'ok' => false,
            'html' => $lastFetched['html'] ?? null,
            'http_status' => $lastFetched['http_status'] ?? null,
            'payload' => $lastFetched['payload'] ?? $this->initialPayload($prefCode, $kensu, $keyword, '0', 'Plus'),
            'attempt_label' => $lastFetched['attempt_label'] ?? 'all_attempts_failed',
            'attempts' => $attempts,
            'error' => $lastFetched['error'] ?? '初回検索で商工会結果行を抽出できなかった。',
            'stop_reason' => '初回検索結果を抽出できなかった。取得ページログを確認して。',
        ];
    }

    private function postConditionAndSearch(string $prefCode, string $prefLabel, int $kensu, string $keyword, array &$attempts): ?array
    {
        $conditionEndpoint = (string) config('discovery.shokokai_web_search_condition_endpoint', 'https://www12.shokokai.or.jp/hpsearch/top/php/zyokensentaku.php');
        $searchEndpoint = (string) config('discovery.shokokai_web_search_endpoint');
        $conditionPayload = [
            'shokokai' => $keyword,
            'kensu' => (string) $kensu,
            'kencdTbl' => $prefCode,
            'loadtype' => '1',
        ];

        $condition = $this->postHtml($conditionEndpoint, $conditionPayload, 'https://www.shokokai.or.jp/?page_id=1754');
        $conditionAttempt = $this->summarizeAttempt('condition_page', $condition, $prefCode, $prefLabel);
        $attempts[] = $conditionAttempt;

        if (empty($condition['ok']) || !is_string($condition['html'] ?? null)) {
            $condition['attempt_label'] = 'condition_page';
            $condition['attempts'] = $attempts;
            $condition['payload'] = $conditionPayload;
            return $condition;
        }

        $formPayloads = $this->extractSearchFormPayloads((string) $condition['html'], $prefCode, $kensu, $keyword);
        if (empty($formPayloads)) {
            return [
                'ok' => false,
                'html' => (string) $condition['html'],
                'http_status' => $condition['http_status'] ?? null,
                'payload' => $conditionPayload,
                'attempt_label' => 'condition_page_no_search_form',
                'attempts' => $attempts,
                'error' => '条件選択ページからsearch.php用フォームを抽出できなかった。',
            ];
        }

        foreach ($formPayloads as $index => $formPayload) {
            $label = 'condition_form_' . ($index + 1);
            $fetched = $this->postHtml($searchEndpoint, $formPayload, $conditionEndpoint);
            $attempt = $this->summarizeAttempt($label, $fetched, $prefCode, $prefLabel);
            $attempts[] = $attempt;

            if (!empty($attempt['row_count'])) {
                $fetched['attempt_label'] = $label;
                $fetched['attempts'] = $attempts;
                $fetched['payload'] = $formPayload;
                return $fetched;
            }
        }

        return [
            'ok' => false,
            'html' => $condition['html'],
            'http_status' => $condition['http_status'] ?? null,
            'payload' => $conditionPayload,
            'attempt_label' => 'condition_forms_no_rows',
            'attempts' => $attempts,
            'error' => '条件選択ページ経由でも商工会結果行を抽出できなかった。',
        ];
    }

    private function summarizeAttempt(string $label, array $fetched, string $prefCode, string $prefLabel): array
    {
        $html = is_string($fetched['html'] ?? null) ? (string) $fetched['html'] : '';
        $parsed = $html !== '' ? $this->parseSearchResult($html, $prefCode, $prefLabel, 1) : ['rows' => [], 'excluded' => []];

        return [
            'label' => $label,
            'ok' => !empty($fetched['ok']),
            'http_status' => $fetched['http_status'] ?? null,
            'row_count' => count($parsed['rows']),
            'excluded_count' => count($parsed['excluded']),
            'has_next_form' => $html !== '' && $this->extractNextPayload($html, $prefCode, 50, '') !== null,
            'error' => $fetched['error'] ?? null,
            'payload' => $fetched['payload'] ?? null,
        ];
    }

    private function initialDirectPayloads(string $prefCode, int $kensu, string $keyword): array
    {
        return [
            'direct_plus_start0' => $this->initialPayload($prefCode, $kensu, $keyword, '0', 'Plus'),
            'direct_blank_start1' => $this->initialPayload($prefCode, $kensu, $keyword, '1', ''),
            'direct_blank_start0' => $this->initialPayload($prefCode, $kensu, $keyword, '0', ''),
            'direct_minimal' => [
                'zyoken' => "'{$prefCode}'",
                'shokokai' => $keyword,
                'kensu' => (string) $kensu,
                'kencdTbl' => $prefCode,
            ],
        ];
    }

    private function initialPayload(string $prefCode, int $kensu, string $keyword, string $startNo, string $pageMode): array
    {
        return [
            'zyoken' => "'{$prefCode}'",
            'shokokai' => $keyword,
            'kensu' => (string) $kensu,
            'startNo' => $startNo,
            'pageMode' => $pageMode,
            'kencdTbl' => $prefCode,
            'loadtype' => '1',
        ];
    }

    private function postHtml(string $url, array $payload, string $referer): array
    {
        try {
            $response = Http::asForm()
                ->withHeaders([
                    'User-Agent' => (string) config('discovery.shokokai_web_search_user_agent', 'TRUSTEPS-CMS-Lab-ShokokaiWebSearch/0.18.8.1'),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Referer' => $referer,
                ])
                ->timeout((int) config('discovery.shokokai_web_search_timeout', 12))
                ->connectTimeout((int) config('discovery.shokokai_web_search_connect_timeout', 6))
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'strict' => false,
                        'referer' => true,
                        'track_redirects' => true,
                    ],
                ])
                ->post($url, $payload);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'html' => null,
                'http_status' => null,
                'payload' => $payload,
                'error' => 'HTTP取得に失敗: ' . $e->getMessage(),
            ];
        }

        if (!$response->successful()) {
            return [
                'ok' => false,
                'html' => null,
                'http_status' => $response->status(),
                'payload' => $payload,
                'error' => 'HTTPステータスが正常ではない: ' . $response->status(),
            ];
        }

        return [
            'ok' => true,
            'html' => $this->responseToUtf8($response),
            'http_status' => $response->status(),
            'payload' => $payload,
            'error' => null,
        ];
    }

    private function responseToUtf8(Response $response): string
    {
        $contentType = strtolower((string) $response->header('Content-Type', ''));
        $bodyLimit = (int) config('discovery.shokokai_web_search_body_limit', 1600000);
        return $this->toUtf8(substr($response->body(), 0, $bodyLimit), $contentType);
    }

    private function extractSearchFormPayloads(string $html, string $prefCode, int $kensu, string $keyword): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $payloads = [];

        foreach ($xpath->query('//form') as $form) {
            if (!$form instanceof DOMElement) {
                continue;
            }

            $name = strtoupper((string) $form->getAttribute('name'));
            $action = strtolower((string) $form->getAttribute('action'));
            if ($name === 'MAEPAGE' || $name === 'ATOPAGE' || $name === 'MINAOSI') {
                continue;
            }
            if ($action !== '' && !str_contains($action, 'search.php')) {
                continue;
            }

            $payload = [];
            foreach ($xpath->query('.//input[@name]', $form) as $input) {
                if (!$input instanceof DOMElement) {
                    continue;
                }
                $type = strtolower((string) $input->getAttribute('type'));
                if (in_array($type, ['submit', 'button', 'image', 'reset'], true)) {
                    continue;
                }
                $payload[(string) $input->getAttribute('name')] = (string) $input->getAttribute('value');
            }

            foreach ($xpath->query('.//select[@name]', $form) as $select) {
                if (!$select instanceof DOMElement) {
                    continue;
                }
                $selectName = (string) $select->getAttribute('name');
                $selected = $xpath->query('.//option[@selected]', $select)->item(0);
                if ($selected instanceof DOMElement) {
                    $payload[$selectName] = (string) $selected->getAttribute('value');
                }
            }

            $payload['zyoken'] = $payload['zyoken'] ?? "'{$prefCode}'";
            $payload['shokokai'] = $keyword;
            $payload['kensu'] = (string) $kensu;
            $payload['startNo'] = $payload['startNo'] ?? '0';
            $payload['pageMode'] = $payload['pageMode'] ?? 'Plus';
            $payload['kencdTbl'] = $payload['kencdTbl'] ?? $prefCode;
            $payload['loadtype'] = $payload['loadtype'] ?? '1';

            $payloads[] = $payload;
        }

        if (empty($payloads)) {
            $payloads[] = $this->initialPayload($prefCode, $kensu, $keyword, '0', 'Plus');
        }

        return $payloads;
    }

    private function parseSearchResult(string $html, string $prefCode, string $prefLabel, int $page): array
    {
        if (trim($html) === '') {
            return ['rows' => [], 'excluded' => []];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $items = $xpath->query('//li[.//a[@href]]');
        $rows = [];
        $excluded = [];
        $index = 0;

        foreach ($items as $item) {
            if (!$item instanceof DOMElement) {
                continue;
            }

            $index++;
            $link = $xpath->query('.//a[@href]', $item)->item(0);
            if (!$link instanceof DOMElement) {
                continue;
            }

            $rawHref = trim((string) $link->getAttribute('href'));
            $url = $this->normalizeCandidateUrl($rawHref);
            $domain = $url ? $this->normalizeHost((string) parse_url($url, PHP_URL_HOST)) : null;
            $name = $this->cleanText((string) $link->textContent);
            $liHtml = (string) ($item->ownerDocument?->saveHTML($item) ?: '');
            $text = $this->cleanText((string) $item->textContent);
            $mapInfo = $this->extractMapInfo($liHtml);

            if ($name === '' && !empty($mapInfo['name'])) {
                $name = (string) $mapInfo['name'];
            }

            $postalCode = $this->extractPostalCode($text);
            $address = $mapInfo['address'] ?? $this->extractAddress($text);
            $tel = $this->extractLabeledValue($text, 'TEL');
            $fax = $this->extractLabeledValue($text, 'FAX');
            $shokokaiCode = $mapInfo['shokokai_code'] ?? null;
            $rawIndex = $this->extractRawIndex($text);

            if ($name !== '' && !str_contains($name, '商工会') && empty($mapInfo['shokokai_code'])) {
                $excluded[] = [
                    'organization_name' => $name,
                    'url' => $url ?: $rawHref,
                    'excluded_reason' => '商工会WEBサーチの結果行ではないリンク。',
                ];
                continue;
            }

            $organizationType = str_contains($name, '連合会') ? 'shokokai_federation' : 'shokokai';
            $category = $organizationType === 'shokokai_federation'
                ? ['key' => 'shokokai_federation', 'label' => '商工会連合会']
                : ['key' => 'shokokai', 'label' => '商工会'];

            $urlValidity = $this->urlValidity($url, $rawHref);
            $duplicateSignals = $url && $domain ? $this->duplicateSignals($url, $domain) : [];
            $isStorable = $urlValidity['valid'] && $url !== null;
            $defaultChecked = $isStorable && empty($duplicateSignals);
            $confidence = $this->confidence($isStorable, $duplicateSignals, $organizationType, $urlValidity['reason']);

            $row = [
                'page' => $page,
                'raw_index' => $rawIndex,
                'pref_code' => $prefCode,
                'pref_label' => $prefLabel,
                'organization_name' => $name,
                'organization_type' => $organizationType,
                'url' => $url ?: $rawHref,
                'raw_href' => $rawHref,
                'url_key' => $url ? $this->normalizeUrlKey($url) : null,
                'normalized_domain' => $domain,
                'postal_code' => $postalCode,
                'address' => $address,
                'tel' => $tel,
                'fax' => $fax,
                'shokokai_code' => $shokokaiCode,
                'category_key' => $category['key'],
                'category_label' => $category['label'],
                'confidence_label' => $confidence['label'],
                'confidence_reason' => $confidence['reason'],
                'recommendation_label' => $confidence['recommendation_label'],
                'recommendation_reason' => $confidence['recommendation_reason'],
                'duplicate_signals' => $duplicateSignals,
                'default_checked' => $defaultChecked,
                'storable' => $isStorable,
                'url_warning' => $urlValidity['valid'] ? null : $urlValidity['reason'],
                'reasons' => array_values(array_filter([
                    '全国商工会WEBサーチの検索結果から取得。',
                    $address ? '住所情報あり。' : null,
                    $tel ? 'TEL情報あり。' : null,
                    $shokokaiCode ? '商工会コードあり。' : null,
                ])),
            ];

            if ($name === '' && !$url) {
                $row['excluded_reason'] = '名称とURLを抽出できない検索結果。';
                $excluded[] = $row;
                continue;
            }

            $rows[] = $row;
        }

        return ['rows' => $rows, 'excluded' => $excluded];
    }

    private function extractNextPayload(string $html, string $prefCode, int $kensu, string $keyword): ?array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $form = $xpath->query('//form[@name="ATOPAGE"]')->item(0);
        if (!$form instanceof DOMElement) {
            return null;
        }

        $payload = [];
        foreach ($xpath->query('.//input[@name]', $form) as $input) {
            if (!$input instanceof DOMElement) {
                continue;
            }
            $payload[(string) $input->getAttribute('name')] = (string) $input->getAttribute('value');
        }

        if (($payload['pageMode'] ?? '') !== 'Plus') {
            return null;
        }

        $payload['zyoken'] = $payload['zyoken'] ?? "'{$prefCode}'";
        $payload['shokokai'] = $keyword;
        $payload['kensu'] = (string) $kensu;
        $payload['startNo'] = $payload['startNo'] ?? '1';
        $payload['pageMode'] = 'Plus';
        $payload['kencdTbl'] = $payload['kencdTbl'] ?? $prefCode;

        return $payload;
    }

    private function extractMapInfo(string $html): array
    {
        if (preg_match("/mapGo\('([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\)/u", $html, $matches)) {
            return [
                'pref_code' => $matches[1] ?? null,
                'shokokai_code' => $matches[2] ?? null,
                'map_type' => $matches[3] ?? null,
                'name' => html_entity_decode($matches[4] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'address' => html_entity_decode($matches[5] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ];
        }

        return [];
    }

    private function normalizeCandidateUrl(string $rawUrl): ?string
    {
        $url = trim(html_entity_decode($rawUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return null;
        }

        $url = preg_replace('/\s+/u', '', $url) ?: $url;
        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        if (str_starts_with($host, '.') || str_contains($host, '..') || !str_contains($host, '.')) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $port . $path . $query;
    }

    private function urlValidity(?string $url, string $rawHref): array
    {
        if ($url === null) {
            return [
                'valid' => false,
                'reason' => 'URLが壊れている、またはhttp/https URLとして解釈できない: ' . $rawHref,
            ];
        }

        return ['valid' => true, 'reason' => null];
    }

    private function confidence(bool $storable, array $duplicateSignals, string $organizationType, ?string $urlWarning): array
    {
        if (!$storable) {
            return [
                'label' => '無効',
                'reason' => $urlWarning ?: '保存可能なURLではない。',
                'recommendation_label' => '保存不可',
                'recommendation_reason' => 'URLを手動確認してから扱う。',
            ];
        }

        if (!empty($duplicateSignals)) {
            return [
                'label' => '要確認',
                'reason' => '既存DBとの重複シグナルあり。',
                'recommendation_label' => '保存前確認',
                'recommendation_reason' => '同一ドメインまたは同一URLが既に登録されている可能性あり。',
            ];
        }

        if ($organizationType === 'shokokai_federation') {
            return [
                'label' => '中',
                'reason' => '県連合会は名簿元として有用だが、地域商工会より上位組織。',
                'recommendation_label' => '保存推奨',
                'recommendation_reason' => '県単位の追加探索入口として使える。',
            ];
        }

        return [
            'label' => '高',
            'reason' => '全国商工会WEBサーチ由来の商工会HP。',
            'recommendation_label' => '保存推奨',
            'recommendation_reason' => '次工程で会員一覧・事業者一覧ページ探索の起点にできる。',
        ];
    }

    private function duplicateSignals(string $url, string $domain): array
    {
        $signals = [];

        try {
            if (SourceRecord::where('source_url', $url)->exists()) {
                $signals[] = 'source_url一致あり';
            }

            if ($domain !== '' && SourceRecord::where('normalized_domain', $domain)->exists()) {
                $signals[] = 'domain一致あり';
            }
        } catch (Throwable) {
            $signals[] = '重複確認を実行できなかった';
        }

        return $signals;
    }

    private function extractRawIndex(string $text): ?int
    {
        if (preg_match('/^\s*(\d+)\./u', $text, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractPostalCode(string $text): ?string
    {
        if (preg_match('/〒\s*([0-9]{3}-[0-9]{4})/u', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractAddress(string $text): ?string
    {
        if (preg_match('/住所\s*(.+?)\s*(?:TEL|FAX|$)/u', $text, $matches)) {
            return trim((string) $matches[1]);
        }

        return null;
    }

    private function extractLabeledValue(string $text, string $label): ?string
    {
        if (preg_match('/' . preg_quote($label, '/') . '\s*([0-9０-９\-ー－\(\)（）]+)\s*/u', $text, $matches)) {
            return $this->normalizePhone((string) $matches[1]);
        }

        return null;
    }

    private function normalizePhone(string $value): string
    {
        $value = mb_convert_kana($value, 'as', 'UTF-8');
        $value = str_replace(['ー', '－', '―'], '-', $value);
        return trim($value);
    }

    private function normalizeHost(string $host): ?string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        return preg_replace('/^www\./i', '', $host) ?: $host;
    }

    private function normalizeUrlKey(string $url): string
    {
        $url = strtolower($url);
        $url = preg_replace('#^https?://www\.#', 'https://', $url) ?: $url;
        return rtrim($url, '/');
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

    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;
        return trim($text);
    }
}
