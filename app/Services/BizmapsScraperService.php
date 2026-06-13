<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BizmapsScraperService
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
    private const SLEEP_SEC  = 1;

    /**
     * ページ完了ごとにコールバックを呼び出しながら一覧を取得
     * @param callable $onPage function(int $fetched, int $total, int $page): void
     */
    public function fetchListWithProgress(string $startUrl, int $limit, callable $onPage): array
    {
        set_time_limit(300);

        $results = [];
        $page    = 1;
        $nextUrl = $startUrl;

        while (count($results) < $limit && $nextUrl) {
            try {
                $html = $this->fetch($nextUrl);
                if (!$html) break;

                $items = $this->parseListPage($html);
                foreach ($items as $item) {
                    $results[] = $item;
                    if (count($results) >= $limit) break;
                }

                $onPage(count($results), $limit, $page);

                $nextUrl = $this->getNextPageUrl($html, $nextUrl, $page);
                $page++;
                if ($nextUrl) sleep(self::SLEEP_SEC);

            } catch (\Throwable $e) {
                Log::warning('BizmapsScraper progress error: ' . $e->getMessage(), ['url' => $nextUrl]);
                break;
            }
        }

        return $results;
    }

    public function fetchList(string $startUrl, int $limit = 50, bool $fetchHp = false): array
    {
        set_time_limit(300);

        $results = [];
        $page    = 1;
        $nextUrl = $startUrl;

        while (count($results) < $limit && $nextUrl) {
            try {
                $html = $this->fetch($nextUrl);
                if (!$html) break;

                $items = $this->parseListPage($html);
                foreach ($items as $item) {
                    $results[] = $item;
                    if (count($results) >= $limit) break;
                }

                $nextUrl = $this->getNextPageUrl($html, $nextUrl, $page);
                $page++;
                if ($nextUrl) sleep(self::SLEEP_SEC);

            } catch (\Throwable $e) {
                Log::warning('BizmapsScraper error: ' . $e->getMessage(), ['url' => $nextUrl]);
                break;
            }
        }

        if ($fetchHp && count($results) > 0) {
            foreach ($results as &$item) {
                if (empty($item['detail_url'])) continue;
                try {
                    $detail = $this->fetchDetailInfo($item['detail_url']);
                    if (!empty($detail['hp_url']))    $item['hp_url']    = $detail['hp_url'];
                    if (!empty($detail['industry']))  $item['industry']  = $detail['industry'];
                } catch (\Throwable $e) {
                    Log::warning('BizmapsScraper detail error: ' . $e->getMessage(), ['url' => $item['detail_url']]);
                }
            }
            unset($item);
        }

        return $results;
    }

    private function parseListPage(string $html): array
    {
        $items = [];

        $doc = new \DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($doc);

        // results__item クラスのliを対象にする
        $listItems = $xpath->query('//li[contains(@class,"results__item")]');

        if ($listItems->length > 0) {
            foreach ($listItems as $li) {
                $item = $this->parseResultsItem($li, $xpath);
                if ($item) $items[] = $item;
            }
            return $items;
        }

        // フォールバック：/item/ リンクから取得
        $links = $xpath->query('//a[contains(@href,"/item/")]');
        $seen  = [];
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (!$href || isset($seen[$href])) continue;
            $seen[$href] = true;

            $fullHref  = strpos($href, 'http') === 0 ? $href : 'https://biz-maps.com' . $href;
            $rawName   = $link->textContent;
            $nameLines = array_filter(array_map('trim', explode("\n", $rawName)));
            $name      = reset($nameLines) ?: '';
            if (mb_strlen($name) < 2) continue;

            $items[] = [
                'name'       => $name,
                'address'    => null,
                'pref'       => null,
                'city'       => null,
                'industry'   => null,
                'hp_url'     => null,
                'detail_url' => $fullHref,
            ];
        }

        return $items;
    }

    /**
     * results__item 構造のliから情報を抽出
     */
    private function parseResultsItem(\DOMNode $li, \DOMXPath $xpath): ?array
    {
        // 会社名
        $nameNode  = $xpath->query('.//div[contains(@class,"results__name")]', $li)->item(0);
        if (!$nameNode) return null;
        $nameLines = array_filter(array_map('trim', explode("\n", $nameNode->textContent)));
        $name      = reset($nameLines) ?: '';
        if (mb_strlen($name) < 2) return null;

        // 詳細URL
        $linkNode  = $xpath->query('.//a[contains(@href,"/item/")]', $li)->item(0);
        if (!$linkNode) return null;
        $href      = $linkNode->getAttribute('href');
        $detailUrl = strpos($href, 'http') === 0 ? $href : 'https://biz-maps.com' . $href;

        // results__table から各フィールドを取得
        $fields  = $this->parseResultsTable($li, $xpath);
        $address = $fields['住所'] ?? null;
        $industry = $fields['業種'] ?? null;

        // 住所から都道府県・市区町村を抽出
        $pref = null;
        $city = null;
        if ($address) {
            if (preg_match('/([^\s]+[都道府県])([^\s]+?(?:市|区|町|村))/u', $address, $m)) {
                $pref = $m[1];
                $city = $m[2];
            }
        }

        return [
            'name'       => $name,
            'address'    => $address ? preg_replace('/\s+/u', ' ', trim($address)) : null,
            'pref'       => $pref,
            'city'       => $city,
            'industry'   => $industry,
            'hp_url'     => null,
            'detail_url' => $detailUrl,
        ];
    }

    /**
     * results__table から th→td のペアを連想配列で返す
     */
    private function parseResultsTable(\DOMNode $li, \DOMXPath $xpath): array
    {
        $fields = [];
        $rows   = $xpath->query('.//table[contains(@class,"results__table")]//tr', $li);

        foreach ($rows as $row) {
            $th = $xpath->query('.//th', $row)->item(0);
            $td = $xpath->query('.//td', $row)->item(0);
            if (!$th || !$td) continue;

            $label = trim($th->textContent);
            $value = trim(preg_replace('/\s+/u', ' ', $td->textContent));
            if ($label && $value && $value !== '-') {
                $fields[$label] = $value;
            }
        }

        return $fields;
    }

    /**
     * 詳細ページからHP URL + 業種を取得
     */
    public function fetchDetailInfo(string $detailUrl): array
    {
        sleep(self::SLEEP_SEC);
        $html = $this->fetch($detailUrl);
        if (!$html) return [];

        $doc = new \DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($doc);

        $result = ['hp_url' => null, 'industry' => null];

        // th/td テーブルから全フィールド取得
        $rows = $xpath->query('//tr');
        foreach ($rows as $row) {
            $th = $xpath->query('.//th', $row)->item(0);
            $td = $xpath->query('.//td', $row)->item(0);
            if (!$th || !$td) continue;

            $label = trim($th->textContent);
            $value = trim($td->textContent);

            if (str_contains($label, 'URL') || str_contains($label, 'ホームページ')) {
                $aTag = $xpath->query('.//a[@href]', $td)->item(0);
                if ($aTag) {
                    $href = $aTag->getAttribute('href');
                    if ($href && str_starts_with($href, 'http') && !str_contains($href, 'biz-maps.com')) {
                        $result['hp_url'] = $href;
                    }
                } elseif (str_starts_with($value, 'http')) {
                    $result['hp_url'] = $value;
                }
            }

            if ($label === '業種' && $value && $value !== '-') {
                $result['industry'] = $value;
            }
        }

        // HP URLが見つからない場合は外部リンクから探す
        if (!$result['hp_url']) {
            $links = $xpath->query('//a[@href]');
            foreach ($links as $link) {
                $href     = $link->getAttribute('href');
                $linkText = trim($link->textContent);
                if (!$href || !str_starts_with($href, 'http')) continue;
                if (str_contains($href, 'biz-maps.com')) continue;
                if (str_contains($href, 'facebook.com')) continue;
                if (str_contains($href, 'instagram.com')) continue;
                if (str_contains($href, 'twitter.com') || str_contains($href, 'x.com')) continue;
                if (str_contains($href, 'google.com')) continue;
                if (str_contains($href, 'youtube.com')) continue;
                if (preg_match('/\.(jpg|jpeg|png|gif|pdf)$/i', $href)) continue;

                if (preg_match('/HP|ホームページ|WEB|Web|サイト|公式/u', $linkText)) {
                    $result['hp_url'] = $href;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * 後方互換：HP URLのみ取得
     */
    public function fetchDetailHpUrl(string $detailUrl): ?string
    {
        $info = $this->fetchDetailInfo($detailUrl);
        return $info['hp_url'] ?? null;
    }

    private function getNextPageUrl(string $html, string $currentUrl, int $currentPage): ?string
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($doc);

        // 「›」next 矢印リンク（disabled でない）を使う
        $links = $xpath->query(
            '//li[contains(@class,"page-item") and contains(@class,"next") and not(contains(@class,"disabled"))]/a[@href]'
        );

        if ($links->length === 0) {
            return null;
        }

        $href = $links->item(0)->getAttribute('href');
        if (!$href) {
            return null;
        }

        if (str_starts_with($href, '/')) {
            $parsed = parse_url($currentUrl);
            $href   = $parsed['scheme'] . '://' . $parsed['host'] . $href;
        }

        return $href;
    }

    private function fetch(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => implode("\r\n", [
                    'User-Agent: ' . self::USER_AGENT,
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: ja,en-US;q=0.9,en;q=0.8',
                    'Accept-Encoding: identity',
                    'Connection: close',
                ]),
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $html = @file_get_contents($url, false, $context);
        return $html ?: null;
    }
}
