<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BizmapsScraperService
{
    private const USER_AGENT = 'Mozilla/5.0 (compatible; TRUSTEPS-CrawlerBot/1.0)';
    private const SLEEP_SEC  = 1;

    /**
     * BIZMAPSの一覧ページから企業情報を取得する
     */
    public function fetchList(string $startUrl, int $limit = 50): array
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

                // 次ページのURL取得
                $nextUrl = $this->getNextPageUrl($html, $nextUrl, $page);
                $page++;

                if ($nextUrl) sleep(self::SLEEP_SEC);

            } catch (\Throwable $e) {
                Log::warning('BizmapsScraper error: ' . $e->getMessage(), ['url' => $nextUrl]);
                break;
            }
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

        // BIZMAPSの一覧：各企業はliタグ内にdlテーブル形式で入っている
        $listItems = $xpath->query('//ul[contains(@class,"list")]/li | //div[contains(@class,"list")]//li');

        // フォールバック：リンクから詳細ページURLを収集
        if ($listItems->length === 0) {
            $listItems = $xpath->query('//a[contains(@href,"/item/")]');
        }

        foreach ($listItems as $li) {
            $item = $this->parseListItem($li, $xpath);
            if ($item) $items[] = $item;
        }

        // さらにフォールバック：/item/ リンクを直接取得
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

                $name = trim($link->textContent);
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
        // 企業名（リンクテキスト）
        $nameNode = $xpath->query('.//a[contains(@href,"/item/")]', $node)->item(0);
        if (!$nameNode) return null;

        $name     = trim($nameNode->textContent);
        $detailHref = $nameNode->getAttribute('href');
        if (mb_strlen($name) < 2) return null;

        $detailUrl = strpos($detailHref, 'http') === 0
            ? $detailHref
            : 'https://biz-maps.com' . $detailHref;

        // テーブル行から情報を取得
        $address  = null;
        $pref     = null;
        $city     = null;
        $industry = null;

        $rows = $xpath->query('.//tr | .//dd', $node);
        $allText = $node->textContent;

        // 住所を正規表現で抽出
        if (preg_match('/〒\s*(\d{3}-\d{4})\s+([^\s]+[都道府県])([^\s]+?(?:市|区|町|村))/u', $allText, $m)) {
            $pref    = $m[2];
            $city    = $m[3];
            $address = $m[0];
        } elseif (preg_match('/([^\s]+[都道府県])([^\s]+?(?:市|区|町|村))/u', $allText, $m)) {
            $pref = $m[1];
            $city = $m[2];
        }

        // 業種テキスト（tdの中から「業種」ラベルの次のtd）
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
            'hp_url'     => null,     // 詳細ページで取得
            'detail_url' => $detailUrl,
        ];
    }

    /**
     * 詳細ページからHP URLを取得する
     */
    public function fetchDetailHpUrl(string $detailUrl): ?string
    {
        try {
            sleep(self::SLEEP_SEC);
            $html = $this->fetch($detailUrl);
            if (!$html) return null;

            $doc = new \DOMDocument();
            @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new \DOMXPath($doc);

            // URLフィールドを探す
            $links = $xpath->query('//td[contains(text(),"URL") or contains(text(),"ホームページ")]/following-sibling::td[1]//a[@href] | //tr[.//th[contains(text(),"URL")]]/td//a[@href]');
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                if ($href && strpos($href, 'http') === 0 && strpos($href, 'biz-maps.com') === false) {
                    return $href;
                }
            }

            // テーブル行でURLを直接探す
            $rows = $xpath->query('//tr');
            foreach ($rows as $row) {
                $ths = $xpath->query('.//th | .//td[1]', $row);
                $label = $ths->item(0) ? trim($ths->item(0)->textContent) : '';
                if (str_contains($label, 'URL') || str_contains($label, 'ホームページ')) {
                    $aTag = $xpath->query('.//td[last()]//a[@href]', $row)->item(0);
                    if ($aTag) {
                        $href = $aTag->getAttribute('href');
                        if ($href && strpos($href, 'http') === 0) {
                            return $href;
                        }
                    }
                    // リンクがない場合はテキストを確認
                    $tdText = trim($xpath->query('.//td[last()]', $row)->item(0)?->textContent ?? '');
                    if (str_starts_with($tdText, 'http')) {
                        return $tdText;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('BizmapsScraper detail error: ' . $e->getMessage(), ['url' => $detailUrl]);
        }

        return null;
    }

    /**
     * 次ページURLを取得
     */
    private function getNextPageUrl(string $html, string $currentUrl, int $currentPage): ?string
    {
        // ページネーションから次ページのphトークン付きURLを取得
        $doc = new \DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($doc);

        $nextPage = $currentPage + 1;

        // "›"リンクまたはpage=N+1のリンクを探す
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
                'method'     => 'GET',
                'header'     => implode("\r\n", [
                    'User-Agent: ' . self::USER_AGENT,
                    'Accept: text/html,application/xhtml+xml',
                    'Accept-Language: ja,en-US;q=0.9',
                    'Connection: close',
                ]),
                'timeout'    => 15,
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
