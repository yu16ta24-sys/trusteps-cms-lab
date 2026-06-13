<?php

namespace App\Support;

/**
 * 会社名の正規化を一元化するヘルパー。
 *
 * 以前は BizmapsImportController / CompanyController / SourceRecordController /
 * DiscoveryLabController でそれぞれ別ロジックの normalizeName() を持っており、
 * 同じ会社名が書き込んだ経路によって異なる name_norm になって名寄せが漏れていた。
 * その最も完全だった BIZMAPS 版ロジックをここに集約する。
 */
class NameNormalizer
{
    /**
     * 法人格・スペース・全角半角・大文字小文字を除去した照合用の正規化名を返す。
     * 空文字や null の場合は空文字を返す（呼び出し側で `?: null` すれば従来の null 挙動を維持できる）。
     */
    public static function normalize(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }

        // 略称除去
        $name = preg_replace('/（株）|（有）|（合）|\(株\)|\(有\)|\(合\)|㈱|㈲/u', '', $name);

        // 法人格（前置き・後置き）除去
        $sfx = '株式会社|有限会社|合同会社|合資会社|合名会社|一般社団法人|一般財団法人|公益社団法人|公益財団法人|特定非営利活動法人|社会福祉法人|医療法人';
        $name = preg_replace('/^(?:' . $sfx . ')\s*/u', '', $name);
        $name = preg_replace('/\s*(?:' . $sfx . ')$/u', '', $name);

        // 全角英数・スペース→半角
        $name = mb_convert_kana($name, 'ans');

        // スペース除去（全角・半角）
        $name = preg_replace('/[\s　]+/u', '', $name);

        return mb_strtolower($name);
    }
}
