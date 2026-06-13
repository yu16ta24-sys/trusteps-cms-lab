<?php

namespace App\Support;

/**
 * URL 正規化を一元化するヘルパー。
 *
 * 以前は各所で parse_url + preg_replace('/^www\./') や strtolower(rtrim($url,'/'))
 * がインラインで乱立し、照合キーと保存キーがズレて重複チェックがすり抜けていた。
 */
class UrlNormalizer
{
    /**
     * 照合キー用の正規化 URL。
     * スキームは保持し、小文字化＋末尾スラッシュ除去のみ行う。
     *
     * 注意: BIZMAPS の domains 照合は SQL 側で
     * `LOWER(TRIM(TRAILING '/' FROM url))` と比較しているため、
     * PHP 側のこの正規化も「小文字化＋末尾スラッシュ除去」に一致させている。
     * ここに www 除去を入れると SQL 側とズレて照合が壊れるので入れない。
     * ホスト単位の正規化（www 除去）が必要な場合は host() を使う。
     */
    public static function normalize(?string $url): string
    {
        if ($url === null || $url === '') {
            return '';
        }

        return strtolower(rtrim(trim($url), '/'));
    }

    /**
     * normalized_domain 用のホスト抽出。小文字化＋先頭 www. 除去。
     */
    public static function host(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        $host = preg_replace('/^www\./', '', strtolower($host));

        return $host !== '' ? $host : null;
    }
}
