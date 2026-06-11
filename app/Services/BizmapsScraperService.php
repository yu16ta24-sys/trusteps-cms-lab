<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BizmapsScraperService
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
    private const SLEEP_SEC  = 1;

    /**
     * BIZMAPSの一覧ページから企業情報を取得する
     */
    public function fetchList(string $startUrl, int $limit = 50, bool $fetchHp = false): array
    {
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

        // 詳細ページからHP URL取得
        if ($fetchHp && count($results) > 0) {
            foreach ($results as &$item) {
                if (empty($item['detail_url'])) continue;
                try {
                    $hp = $this->fetchDetailHpUrl($item['detail_url']);
                    if ($hp) $item['hp_url'] = $hp;
                } catch (\Throwable $e) {
                    Log::warning('BizmapsScraper detail error: ' . $e->getMessage(), ['url' => $item['detail_url']]);
                }
            }
            unset($item);
        }

        return $results;
    }

    /**
     * 一覧ページHTMLから企業情報を抽出する
     */
    private function parseListPage(string $html): array
    {
        $items = [];

        $doc = new \DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($doc);

        $listItems = $xpath->query('//ul[contains(@class,"list")]/li | //div[contains(@class,"list")]//li');

        if ($listItems->length === 0) {
            $listItems = $xpath->query('//a[contains(@href,"/item/")]');
        }

        foreach ($listItems as $li) {
            $item = $this->parseListItem($li, $xpath);
            if ($item) $items[] = $item;
        }

        // フォールバック：/item/ リンクを直接取得
        if (empty($items)) {
            $links = $xpath->query('//a[contains(@href,"/item/")]');
            $seen  = [];
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                if (!$href || isset($seen[$href])) continue;
                $seen[$href] = true;

                $fullHref = strpos($href, 'http') === 0
                    ? $href
                    : 'https://biz-maps.com' . $href;

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
        }

        return $items;
    }

    /**
     * li要素から1件分の企業情報を抽出
     */
    private function parseListItem(\DOMNode $node, \DOMXPath $xpath): ?array
    {
        $nameNode = $xpath->query('.//a[contains(@href,"/item/")]', $node)->item(0);
        if (!$nameNode) return null;

        $rawName   = $nameNode->textContent;
        $nameLines = array_filter(array_map('trim', explode("\n", $rawName)));
        $name      = reset($nameLines) ?: '';
        $detailHref = $nameNode->getAttribute('href');
        if (mb_strlen($name) < 2) return null;

        $detailUrl = strpos($detailHref, 'http') === 0
            ? $detailHref
            : 'https://biz-maps.com' . $detailHref;

        $address  = null;
        $pref     = null;
        $city     = null;
        $industry = null;

        $allText = $node->textContent;

        if (preg_match('/〒\s*\d{3}-\d{4}\s+([^\s]+[都道府県])([^\s]+?(?:市|区|町|村))/u', $allText, $m)) {
            $pref    = $m[1];
            $city    = $m[2];
            $address = trim($m[0]);
        } elseif (preg_match('/([^\s]+[都道府県])([^\s]+?(?:市|区|町|村))/u', $allText, $m)) {
            $pref = $m[1];
            $city = $m[2];
        }

        $tds = $xpath->query('.//td | .//dd', $node);
        $prevLabel = '';
        foreach ($tds as $td) {
            $text = trim($td->textContent);
            if ($prevLabel === '業種' && !empty($text) && strpos($text, '〒') === false) {
                $industry = $text;
                break;
            }
            $prevLabel = $text;
        }

        return [
            'name'       => $name,
            'address'    => $address,
            'pref'       => $pref,
            'city'       => $city,
            'industry'   => $industry,
            'hp_url'     => null,
            'detail_url' => $detailUrl,
        ];
    }

    /**
     * 詳細ページからHP URLを取得する
     */
    public function fetchDetailHpUrl(string $detailUrl): ?string
    {
        sleep(self::SLEEP_SEC);
        $html = $this->fetch($detailUrl);
        if (!$html) return null;

        $doc = new \DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($doc);

        // URLラベルの隣のリンクを探す
        $rows = $xpath->query('//tr');
        foreach ($rows as $row) {
            $th = $xpath->query('.//th', $row)->item(0);
            if (!$th) continue;
            $label = trim($th->textContent);
            if (!str_contains($label, 'URL') && !str_contains($label, 'ホームページ')) continue;

            $aTag = $xpath->query('.//td//a[@href]', $row)->item(0);
            if ($aTag) {
                $href = $aTag->getAttribute('href');
                if ($href && strpos($href, 'http') === 0 && !str_contains($href, 'biz-maps.com')) {
                    return $href;
                }
            }

            $tdText = trim($xpath->query('.//td', $row)->item(0)?->textContent ?? '');
            if (str_starts_with($tdText, 'http')) {
                return $tdText;
            }
        }

        // お問い合わせURLフィールド以外のリンクを探す
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

            // HPリンクテキストなら優先
            if (preg_match('/HP|ホームページ|WEB|Web|サイト|公式/u', $linkText)) {
                return $href;
            }
        }

        return null;
    }

    /**
     * 次ページURLを取得
     */
    private function getNextPageUrl(string $html, string $currentUrl, int $currentPage): ?string
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($doc);

        $nextPage = $currentPage + 1;
        $links = $xpath->query('//a[contains(@href,"page=' . $nextPage . '")]');
        if ($links->length > 0) {
            $href = $links->item(0)->getAttribute('href');
            if ($href) {
                return strpos($href, 'http') === 0
                    ? $href
                    : 'https://biz-maps.com' . $href;
            }
        }

        return null;
    }

    /**
     * HTTPフェッチ
     */
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
