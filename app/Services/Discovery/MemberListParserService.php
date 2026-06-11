<?php

namespace App\Services\Discovery;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

class MemberListParserService
{
    public function __construct(private readonly UrlClassifier $urlClassifier)
    {
    }

    public function extractBusinesses(string $html, string $pageUrl, string $directorySourceUrl = ''): array
    {
        [$doc, $xpath] = $this->makeDom($html);

        $strategies = [
            'table_row' => $this->extractFromTable($xpath, $pageUrl, $directorySourceUrl),
            'ul_li' => $this->extractFromListItems($xpath, $pageUrl, $directorySourceUrl),
            'div_card' => $this->extractFromCards($xpath, $pageUrl, $directorySourceUrl),
            'article' => $this->extractFromArticles($xpath, $pageUrl, $directorySourceUrl),
            'dl' => $this->extractFromDefinitionLists($xpath, $pageUrl, $directorySourceUrl),
        ];

        $merged = [];
        foreach ($strategies as $method => $items) {
            foreach ($items as $item) {
                $key = $this->candidateKey($item);
                if ($key === '') {
                    continue;
                }
                if (! isset($merged[$key]) || ($item['url_confidence'] ?? 0) > ($merged[$key]['url_confidence'] ?? 0)) {
                    $item['extraction_method'] = $method;
                    $merged[$key] = $item;
                }
            }
        }

        return array_values($merged);
    }

    private function extractFromTable(DOMXPath $xpath, string $pageUrl, string $directorySourceUrl): array
    {
        $results = [];
        foreach ($xpath->query('//table//tr') as $row) {
            $text = $this->squash($row->textContent);
            if ($text === '' || mb_strlen($text) < 6) {
                continue;
            }
            if ($this->looksLikeHeader($text)) {
                continue;
            }
            $candidate = $this->parseNode($row, $xpath, $pageUrl, $directorySourceUrl);
            if ($candidate) {
                $results[] = $candidate;
            }
        }
        return $results;
    }

    private function extractFromListItems(DOMXPath $xpath, string $pageUrl, string $directorySourceUrl): array
    {
        $results = [];
        foreach ($xpath->query('//li') as $item) {
            $text = $this->squash($item->textContent);
            if (mb_strlen($text) < 6 || mb_strlen($text) > 1000) {
                continue;
            }
            if (! $this->nodeHasBusinessSignals($item, $xpath)) {
                continue;
            }
            $candidate = $this->parseNode($item, $xpath, $pageUrl, $directorySourceUrl);
            if ($candidate) {
                $results[] = $candidate;
            }
        }
        return $results;
    }

    private function extractFromCards(DOMXPath $xpath, string $pageUrl, string $directorySourceUrl): array
    {
        $results = [];
        $query = "//*[contains(@class,'shop') or contains(@class,'member') or contains(@class,'card') or contains(@class,'store') or contains(@class,'company') or contains(@class,'business') or contains(@class,'entry') or contains(@class,'item') or contains(@class,'post')]";
        foreach ($xpath->query($query) as $node) {
            $text = $this->squash($node->textContent);
            if (mb_strlen($text) < 8 || mb_strlen($text) > 2000) {
                continue;
            }
            if ($xpath->query('.//*', $node)->length > 80) {
                continue;
            }
            if (! $this->nodeHasBusinessSignals($node, $xpath)) {
                continue;
            }
            $candidate = $this->parseNode($node, $xpath, $pageUrl, $directorySourceUrl);
            if ($candidate) {
                $results[] = $candidate;
            }
        }
        return $results;
    }

    private function extractFromArticles(DOMXPath $xpath, string $pageUrl, string $directorySourceUrl): array
    {
        $results = [];
        foreach ($xpath->query('//article') as $article) {
            $text = $this->squash($article->textContent);
            if (mb_strlen($text) < 8 || mb_strlen($text) > 2200) {
                continue;
            }
            $candidate = $this->parseNode($article, $xpath, $pageUrl, $directorySourceUrl);
            if ($candidate) {
                $results[] = $candidate;
            }
        }
        return $results;
    }

    private function extractFromDefinitionLists(DOMXPath $xpath, string $pageUrl, string $directorySourceUrl): array
    {
        $results = [];
        foreach ($xpath->query('//dl') as $dl) {
            $text = $this->squash($dl->textContent);
            if (mb_strlen($text) < 8 || mb_strlen($text) > 1500) {
                continue;
            }
            if (! $this->nodeHasBusinessSignals($dl, $xpath)) {
                continue;
            }
            $candidate = $this->parseNode($dl, $xpath, $pageUrl, $directorySourceUrl);
            if ($candidate) {
                $results[] = $candidate;
            }
        }
        return $results;
    }

    private function parseNode(DOMNode $node, DOMXPath $xpath, string $pageUrl, string $directorySourceUrl): ?array
    {
        $text = $this->squash($node->textContent);
        $businessName = $this->extractBusinessName($node, $xpath, $text);
        $address = $this->extractAddress($text);
        $tel = $this->extractTel($text);
        $fax = $this->extractFax($text);
        $businessType = $this->extractBusinessType($text);
        $description = Str::limit($text, 300, '');

        $bestUrl = null;
        $bestUrlType = null;
        $bestConfidence = 0;
        $sns = [];
        $detailPageUrl = null;

        foreach ($xpath->query('.//a[@href]', $node) as $link) {
            $href = $link->getAttribute('href');
            $absolute = $this->urlClassifier->normalizeAbsoluteUrl($href, $pageUrl);
            if (! $absolute) {
                continue;
            }
            $linkText = $this->squash($link->textContent);
            $class = $this->urlClassifier->classify($absolute, $pageUrl, $directorySourceUrl, $linkText);
            if ($class['is_business_candidate'] && $class['confidence'] >= $bestConfidence) {
                $bestUrl = $absolute;
                $bestUrlType = $class['type'];
                $bestConfidence = (int) $class['confidence'];
            } elseif (str_starts_with($class['type'], 'sns_')) {
                $sns[] = ['url' => $absolute, 'type' => $class['type']];
            } elseif ($class['type'] === 'internal' && preg_match('/詳細|詳しく|more|profile|店舗|会員|事業/u', $linkText)) {
                $detailPageUrl = $absolute;
            }
        }

        if ($businessName === null && $bestUrl !== null) {
            $businessName = $this->guessNameFromUrl($bestUrl);
        }

        if ($businessName === null && $address === null && $tel === null && $bestUrl === null && empty($sns)) {
            return null;
        }

        return [
            'business_name' => $businessName,
            'business_name_kana' => null,
            'address' => $address,
            'tel' => $tel,
            'fax' => $fax,
            'business_type' => $businessType,
            'description' => $description,
            'url_candidate' => $bestUrl,
            'url_hash' => $bestUrl ? sha1($bestUrl) : null,
            'normalized_domain' => $bestUrl ? $this->urlClassifier->normalizedDomain($bestUrl) : null,
            'url_type' => $bestUrlType,
            'url_confidence' => $bestConfidence,
            'sns_urls' => $sns,
            'detail_page_url' => $detailPageUrl,
            'raw_html_block' => Str::limit($node->ownerDocument?->saveHTML($node) ?? '', 65000, ''),
            'raw_json' => [
                'text_excerpt' => $description,
                'page_url' => $pageUrl,
                'directory_source_url' => $directorySourceUrl,
            ],
        ];
    }

    private function nodeHasBusinessSignals(DOMNode $node, DOMXPath $xpath): bool
    {
        $text = $this->squash($node->textContent);
        if (preg_match('/〒\s*\d{3}-?\d{4}|住所|TEL|電話|業種|営業時間|代表/u', $text)) {
            return true;
        }
        return $xpath->query('.//a[@href]', $node)->length > 0 && mb_strlen($text) >= 8;
    }

    private function extractBusinessName(DOMNode $node, DOMXPath $xpath, string $text): ?string
    {
        foreach (['.//h1', './/h2', './/h3', './/h4', './/strong', './/b'] as $query) {
            $candidate = $xpath->query($query, $node)->item(0);
            if ($candidate) {
                $name = $this->cleanName($candidate->textContent);
                if ($name !== '') {
                    return $name;
                }
            }
        }

        $firstLink = $xpath->query('.//a[@href]', $node)->item(0);
        if ($firstLink) {
            $name = $this->cleanName($firstLink->textContent);
            if ($name !== '' && ! preg_match('/HP|ホームページ|詳細|map|MAP|Google|Instagram|Facebook/u', $name)) {
                return $name;
            }
        }

        $line = trim((string) preg_split('/\R/u', $text)[0]);
        $line = $this->cleanName($line);
        if ($line !== '' && mb_strlen($line) <= 60) {
            return $line;
        }

        return null;
    }

    private function extractAddress(string $text): ?string
    {
        if (preg_match('/〒\s*\d{3}-?\d{4}\s*([^\n\r]{5,80})/u', $text, $m)) {
            return $this->squash('〒'.$m[0]);
        }
        if (preg_match('/(北海道|東京都|京都府|大阪府|.{2,3}県)[^\n\r]{4,80}(市|区|町|村)[^\n\r]{0,80}/u', $text, $m)) {
            return $this->squash($m[0]);
        }
        return null;
    }

    private function extractTel(string $text): ?string
    {
        if (preg_match('/(?:TEL|電話|Tel|tel|ＴＥＬ)[：:\s]*([0-9０-９\-ー－()（）]{8,20})/u', $text, $m)) {
            return mb_convert_kana($m[1], 'n');
        }
        if (preg_match('/0\d{1,4}[-ー－]\d{1,4}[-ー－]\d{3,4}/u', $text, $m)) {
            return mb_convert_kana($m[0], 'n');
        }
        return null;
    }

    private function extractFax(string $text): ?string
    {
        if (preg_match('/(?:FAX|Fax|fax|ＦＡＸ)[：:\s]*([0-9０-９\-ー－()（）]{8,20})/u', $text, $m)) {
            return mb_convert_kana($m[1], 'n');
        }
        return null;
    }

    private function extractBusinessType(string $text): ?string
    {
        if (preg_match('/(?:業種|業務内容|事業内容)[：:\s]*([^\n\r]{2,60})/u', $text, $m)) {
            return $this->squash($m[1]);
        }
        return null;
    }

    private function cleanName(?string $value): string
    {
        $value = $this->squash((string) $value);
        $value = preg_replace('/^(\d+\.|\d+\)|No\.\d+\s*)/u', '', $value);
        $value = trim((string) $value, " \t\n\r\0\x0B｜|/／：:");
        return mb_substr($value, 0, 255);
    }

    private function guessNameFromUrl(string $url): ?string
    {
        $host = $this->urlClassifier->normalizedDomain($url);
        return $host ? str_replace(['.co.jp', '.jp', '.com', '.net'], '', $host) : null;
    }

    private function candidateKey(array $candidate): string
    {
        if (!empty($candidate['url_candidate'])) {
            return 'url:'.sha1($candidate['url_candidate']);
        }
        $parts = array_filter([
            $candidate['business_name'] ?? null,
            $candidate['tel'] ?? null,
            $candidate['address'] ?? null,
        ]);
        return $parts ? 'text:'.sha1(implode('|', $parts)) : '';
    }

    private function looksLikeHeader(string $text): bool
    {
        return mb_strlen($text) <= 80 && preg_match('/店舗名|会社名|事業所名|住所|電話|TEL|業種/u', $text);
    }

    private function makeDom(string $html): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        return [$doc, new DOMXPath($doc)];
    }

    private function squash(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
