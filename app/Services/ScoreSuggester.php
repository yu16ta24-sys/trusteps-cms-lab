<?php

namespace App\Services;

use App\Models\Company;

class ScoreSuggester
{
    public const ALGO = 'suggest_v2';

    private const DEV_DIFFICULTY = [
        'lodging'         => 5,
        'lodging_leisure' => 5,
        'medical'         => 4,
        'food'           => 4,
        'beauty'         => 4,
        'real_estate'    => 4,
        'retail'         => 3,
        'automotive'     => 3,
        'therapy'        => 3,
        'btob_service'   => 2,
        'manufacturing'  => 2,
        'welfare_care'   => 2,
        'child_education' => 2,
        'culture_event'  => 2,
        'local_service'  => 2,
        'agriculture'    => 2,
        'construction'   => 1,
        'exterior_paint' => 1,
        'professional'   => 1,
    ];

    private const SELF_UPDATE_FIT = [
        'construction'    => 5,
        'exterior_paint'  => 5,
        'professional'    => 4,
        'welfare_care'    => 4,
        'btob_service'    => 3,
        'child_education' => 3,
        'culture_event'   => 3,
        'food'            => 3,
        'beauty'          => 3,
        'therapy'         => 3,
        'manufacturing'   => 2,
        'local_service'   => 2,
        'retail'          => 2,
        'automotive'      => 2,
        'real_estate'     => 2,
        'agriculture'     => 2,
        'medical'         => 2,
        'lodging'         => 2,
        'lodging_leisure' => 2,
    ];

    /**
     * @return array<string, array{value:int|null, confidence:string, basis:string, drivers:array<int, string>, note:string}>
     */
    public function suggest(Company $company): array
    {
        // 最新のHPファクトを取得
        $hpFact = $this->getLatestHpFact($company);

        return [
            'hp_weakness'      => $this->suggestHpWeakness($company, $hpFact),
            'self_update_fit'  => $this->suggestSelfUpdateFit($company, $hpFact),
            'dev_difficulty'   => $this->byIndustry($company, self::DEV_DIFFICULTY, '業種ベースの初期見込み。予約・決済・在庫・規制の絡みやすさを反映。'),
            'portal_dependence' => $this->suggestPortalDependence($company, $hpFact),
        ];
    }

    /**
     * HP弱点度の提案（HP解析結果があれば高精度、なければnull）
     */
    private function suggestHpWeakness(Company $company, ?object $hpFact): array
    {
        if (!$hpFact) {
            return $this->none('HP解析未実施。company詳細画面の「HP解析」ボタンで解析するとこの軸の自動提案が生成されます。');
        }

        $score = $hpFact->hp_improvement_score ?? 0;
        $drivers = [];
        $notes = [];

        if (!$hpFact->ssl_enabled) {
            $drivers[] = 'ssl_missing';
            $notes[] = 'SSL未対応';
        }
        if (!$hpFact->mobile_friendly) {
            $drivers[] = 'no_viewport';
            $notes[] = 'スマホ非対応';
        }
        if ($hpFact->update_status === 'stale_2y') {
            $drivers[] = 'stale_2y';
            $notes[] = '2年以上更新なし';
        } elseif ($hpFact->update_status === 'stale_1y') {
            $drivers[] = 'stale_1y';
            $notes[] = '1年以上更新なし';
        }
        if ($hpFact->cms_type === 'unknown') {
            $drivers[] = 'no_cms';
            $notes[] = 'CMS未使用の可能性';
        }
        if (!$hpFact->has_contact_form && !$hpFact->has_public_email && !$hpFact->has_phone) {
            $drivers[] = 'no_contact';
            $notes[] = '問い合わせ導線なし';
        }

        if (empty($drivers)) {
            $notes[] = 'HP解析の結果、大きな弱点は検出されませんでした';
        }

        return [
            'value'      => min(5, $score),
            'confidence' => '0.6',
            'basis'      => 'hp_analysis',
            'drivers'    => $drivers,
            'note'       => 'HP解析結果から算出。' . implode('、', $notes) . '。',
        ];
    }

    /**
     * 自走更新化適性の提案（HP解析 + 業種）
     */
    private function suggestSelfUpdateFit(Company $company, ?object $hpFact): array
    {
        $industryBase = $this->byIndustry($company, self::SELF_UPDATE_FIT, '');

        if (!$hpFact) {
            return array_merge($industryBase, [
                'note' => '業種ベースの初期見込み。HP解析実施後に精度が上がります。',
            ]);
        }

        $score = $industryBase['value'] ?? 3;
        $drivers = $industryBase['drivers'];
        $notes = [];

        // お知らせ系があれば +1
        if ($hpFact->hp_has_news) {
            $score = min(5, $score + 1);
            $drivers[] = 'has_news_section';
            $notes[] = 'お知らせ系セクション検出';
        }

        // 採用情報があれば +0.5 → 切り捨て
        if ($hpFact->has_recruiting) {
            $drivers[] = 'has_recruiting';
            $notes[] = '採用情報あり';
        }

        // 更新が止まっていれば余地あり
        if (in_array($hpFact->update_status, ['stale_1y', 'stale_2y'])) {
            $score = min(5, $score + 1);
            $drivers[] = 'update_stopped';
            $notes[] = '更新停止中（自走化余地大）';
        }

        $noteStr = '業種+HP解析から算出。';
        if (!empty($notes)) {
            $noteStr .= implode('、', $notes) . '。';
        }

        return [
            'value'      => $score,
            'confidence' => '0.6',
            'basis'      => 'industry+hp_analysis',
            'drivers'    => $drivers,
            'note'       => $noteStr,
        ];
    }

    /**
     * ポータル依存リスクの提案（HP解析 > domainフォールバック）
     */
    private function suggestPortalDependence(Company $company, ?object $hpFact): array
    {
        if ($hpFact) {
            $level = $hpFact->portal_dependency_level ?? 'none';
            $portalLinks = $hpFact->hp_portal_links ?? null;

            $valueMap = ['none' => 1, 'low' => 2, 'medium' => 3, 'high' => 5];
            $value = $valueMap[$level] ?? 2;

            $drivers = [];
            if ($hpFact->hp_has_tabelog)   $drivers[] = 'tabelog';
            if ($hpFact->hp_has_hotpepper) $drivers[] = 'hotpepper';
            if ($hpFact->hp_has_jalan)     $drivers[] = 'jalan';
            if ($hpFact->hp_has_suumo)     $drivers[] = 'suumo';

            $note = 'HP解析結果から算出。';
            if (!empty($drivers)) {
                $note .= '検出ポータル：' . implode('、', $drivers) . '。';
            } else {
                $note .= 'ポータルリンクは検出されませんでした。';
            }

            return [
                'value'      => $value,
                'confidence' => '0.6',
                'basis'      => 'hp_analysis',
                'drivers'    => $drivers,
                'note'       => $note,
            ];
        }

        // HP解析なし → domainベースのフォールバック
        return $this->byPortalDomain($company);
    }

    /**
     * @param array<string, int> $map
     * @return array{value:int|null, confidence:string, basis:string, drivers:array<int, string>, note:string}
     */
    private function byIndustry(Company $company, array $map, string $note): array
    {
        $industry = $company->industry;
        if (!$industry) {
            return $this->none('業種未設定のため自動提案なし。');
        }

        $slug = $industry->slug;

        // 直接マッチ
        if (array_key_exists($slug, $map)) {
            return [
                'value'      => $map[$slug],
                'confidence' => '0.3',
                'basis'      => 'auto',
                'drivers'    => ['industry:' . $slug],
                'note'       => $note,
            ];
        }

        // サブカテゴリの場合は親スラッグにフォールバック
        $parentSlug = $industry->parent?->slug;
        if ($parentSlug !== null && array_key_exists($parentSlug, $map)) {
            return [
                'value'      => $map[$parentSlug],
                'confidence' => '0.3',
                'basis'      => 'auto',
                'drivers'    => ['industry:' . $slug],
                'note'       => $note,
            ];
        }

        return $this->none('業種未設定または未対応業種のため自動提案なし。');
    }

    private function byPortalDomain(Company $company): array
    {
        $domains = $company->relationLoaded('domains')
            ? $company->domains
            : $company->domains()->get();

        if ($domains->isEmpty()) {
            return $this->none('ドメイン未登録のため自動提案なし。');
        }

        $own    = $domains->where('is_portal', false)->count();
        $portal = $domains->where('is_portal', true)->count();

        if ($own > 0 && $portal === 0) {
            return [
                'value' => 1, 'confidence' => '0.6', 'basis' => 'auto',
                'drivers' => ['own_domain'],
                'note'    => '自社ドメインのみ登録。ポータル/SNS依存は低めに見積もる。',
            ];
        }

        if ($portal > 0 && $own === 0) {
            return [
                'value' => 5, 'confidence' => '0.6', 'basis' => 'auto',
                'drivers' => ['portal_only'],
                'note'    => '登録ドメインがポータル/SNSのみ。自社HPより外部依存が強い可能性。',
            ];
        }

        return [
            'value' => 3, 'confidence' => '0.6', 'basis' => 'auto',
            'drivers' => ['mixed'],
            'note'    => '自社ドメインとポータル/SNSが併存。中間値として提案。',
        ];
    }

    /**
     * 最新のHPファクトを取得する
     */
    private function getLatestHpFact(Company $company): ?object
    {
        $domain = $company->primaryDomain;
        if (!$domain) {
            return null;
        }

        return \App\Models\HpFact::query()
            ->join('hp_snapshots', 'hp_facts.hp_snapshot_id', '=', 'hp_snapshots.id')
            ->where('hp_snapshots.domain_id', $domain->id)
            ->whereNotNull('hp_facts.extracted_at')
            ->orderByDesc('hp_facts.extracted_at')
            ->select('hp_facts.*')
            ->first();
    }

    /**
     * @return array{value:int|null, confidence:string, basis:string, drivers:array<int, string>, note:string}
     */
    private function none(string $note): array
    {
        return [
            'value'      => null,
            'confidence' => '0.3',
            'basis'      => 'auto',
            'drivers'    => [],
            'note'       => $note,
        ];
    }
}
