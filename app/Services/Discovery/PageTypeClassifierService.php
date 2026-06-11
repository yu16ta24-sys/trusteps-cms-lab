<?php

namespace App\Services\Discovery;

use DOMDocument;
use DOMXPath;

class PageTypeClassifierService
{
    private const POSITIVE_KEYWORDS = [
        '会員一覧' => 35,
        '会員紹介' => 35,
        '会員事業所' => 35,
        '事業者一覧' => 35,
        '事業所一覧' => 35,
        '加盟店' => 30,
        '店舗一覧' => 30,
        '店舗紹介' => 30,
        'お店紹介' => 28,
        'お店を探す' => 28,
        '地域のお店' => 28,
        '企業紹介' => 25,
        '事業所検索' => 25,
        '会員' => 15,
        '事業者' => 15,
        '店舗' => 12,
        'お店' => 12,
    ];

    private const URL_KEYWORDS = [
        'member' => 25,
        'members' => 25,
        'kaiin' => 25,
        'shop' => 20,
        'store' => 20,
        'tenpo' => 20,
        'company' => 18,
        'kigyou' => 18,
        'jigyosha' => 18,
        'business' => 15,
        'shoukai' => 15,
        'list' => 12,
        'ichiran' => 12,
        'search' => 12,
    ];

    private const NOISE_KEYWORDS = [
        'お問い合わせ' => -25,
        '問合せ' => -25,
        'アクセス' => -15,
        '入会' => -20,
        '補助金' => -15,
        '共済' => -15,
        '青年部' => -12,
        '女性部' => -12,
        'お知らせ' => -12,
        '新着情報' => -12,
        'イベント' => -12,
        'プライバシー' => -25,
    ];

    public function classify(string $url, string $html = '', string $linkText = ''): array
    {
        $score = 0;
        $reasons = [];
        $negative = [];

        $urlPath = strtolower((string) parse_url($url, PHP_URL_PATH));
        foreach (self::URL_KEYWORDS as $keyword => $point) {
            if (str_contains($urlPath, strtolower($keyword))) {
                $score += $point;
                $reasons[] = 'URL:'.$keyword;
            }
        }

        $text = $linkText;
        $headText = '';
        $bodyText = '';
        $externalLinkCount = 0;

        if ($html !== '') {
            [$doc, $xpath] = $this->makeDom($html);
            $headNodes = $xpath->query('//title|//h1|//h2|//h3');
            foreach ($headNodes as $node) {
                $headText .= ' '.$this->squash($node->textContent);
            }
            $bodyText = $this->squash($doc->textContent ?? '');
            $externalLinkCount = $this->countLinks($xpath, $url, true);
        }

        $targetText = $text.' '.$headText.' '.mb_substr($bodyText, 0, 3000);
        foreach (self::POSITIVE_KEYWORDS as $keyword => $point) {
            if (str_contains($targetText, $keyword)) {
                $score += $point;
                $reasons[] = 'KW:'.$keyword;
            }
        }

        foreach (self::NOISE_KEYWORDS as $keyword => $point) {
            if (str_contains($targetText, $keyword)) {
                $score += $point;
                $negative[] = $keyword;
            }
        }

        $addressCount = substr_count($bodyText, '〒') + substr_count($bodyText, '住所');
        $telCount = preg_match_all('/TEL|電話|ＴＥＬ|Tel|tel/u', $bodyText);
        if ($addressCount >= 3) {
            $add = min(30, $addressCount * 4);
            $score += $add;
            $reasons[] = '住所反復:'.$addressCount;
        }
        if ($telCount >= 3) {
            $add = min(30, $telCount * 4);
            $score += $add;
            $reasons[] = 'TEL反復:'.$telCount;
        }
        if ($externalLinkCount >= 3) {
            $add = min(30, $externalLinkCount * 5);
            $score += $add;
            $reasons[] = '外部リンク:'.$externalLinkCount;
        }

        $score = max(0, min(100, $score));
        $type = match (true) {
            $score >= 65 => 'member_list',
            $score >= 45 => 'member_list_candidate',
            $score >= 25 => 'general_candidate',
            default => 'noise',
        };

        return [
            'page_type' => $type,
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
            'negative' => array_values(array_unique($negative)),
            'external_link_count' => $externalLinkCount,
        ];
    }

    private function countLinks(DOMXPath $xpath, string $baseUrl, bool $externalOnly): int
    {
        $baseHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $count = 0;
        foreach ($xpath->query('//a[@href]') as $link) {
            $href = $link->getAttribute('href');
            $host = strtolower((string) parse_url($href, PHP_URL_HOST));
            if ($host === '') {
                continue;
            }
            if (! $externalOnly || $host !== $baseHost) {
                $count++;
            }
        }
        return $count;
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
        return trim(preg_replace('/\s+/u', ' ', (string) $text));
    }
}
