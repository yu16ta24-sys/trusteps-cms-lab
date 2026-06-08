<?php

namespace App\Services;

use App\Models\Company;

class ScoreSuggester
{
    public const ALGO = 'suggest_v1';

    /**
     * 業種スラッグ → dev_difficulty 初期見込み。
     * これは確定診断ではなく、HP観測データが入る前の軽い初期提案。
     */
    private const DEV_DIFFICULTY = [
        'lodging' => 5,
        'medical' => 4,
        'food' => 4,
        'beauty' => 4,
        'real_estate' => 4,
        'retail' => 3,
        'automotive' => 3,
        'therapy' => 3,
        'btob_service' => 2,
        'manufacturing' => 2,
        'welfare_care' => 2,
        'child_education' => 2,
        'culture_event' => 2,
        'local_service' => 2,
        'agriculture' => 2,
        'construction' => 1,
        'exterior_paint' => 1,
        'professional' => 1,
    ];

    /**
     * 業種スラッグ → self_update_fit 初期見込み。
     * 施工事例・相談事例・お知らせ等の型化しやすさをざっくり見る。
     */
    private const SELF_UPDATE_FIT = [
        'construction' => 5,
        'exterior_paint' => 5,
        'professional' => 4,
        'welfare_care' => 4,
        'btob_service' => 3,
        'child_education' => 3,
        'culture_event' => 3,
        'food' => 3,
        'beauty' => 3,
        'therapy' => 3,
        'manufacturing' => 2,
        'local_service' => 2,
        'retail' => 2,
        'automotive' => 2,
        'real_estate' => 2,
        'agriculture' => 2,
        'medical' => 2,
        'lodging' => 2,
    ];

    /**
     * @return array<string, array{value:int|null, confidence:string, basis:string, drivers:array<int, string>, note:string}>
     */
    public function suggest(Company $company): array
    {
        return [
            'hp_weakness' => $this->none('HP観測データが未取得のため自動提案なし。HPを目視して手動評価する。'),
            'self_update_fit' => $this->byIndustry($company, self::SELF_UPDATE_FIT, '業種ベースの初期見込み。HP観測データが入ったら上書き前提。'),
            'dev_difficulty' => $this->byIndustry($company, self::DEV_DIFFICULTY, '業種ベースの初期見込み。予約・決済・在庫・規制の絡みやすさを軽く反映。'),
            'portal_dependence' => $this->byPortal($company),
        ];
    }

    /**
     * @param array<string, int> $map
     * @return array{value:int|null, confidence:string, basis:string, drivers:array<int, string>, note:string}
     */
    private function byIndustry(Company $company, array $map, string $note): array
    {
        $slug = $company->industry?->slug;

        if ($slug === null || !array_key_exists($slug, $map)) {
            return $this->none('業種未設定または未対応業種のため自動提案なし。');
        }

        return [
            'value' => $map[$slug],
            'confidence' => '0.3',
            'basis' => 'auto',
            'drivers' => ['industry:' . $slug],
            'note' => $note,
        ];
    }

    /**
     * @return array{value:int|null, confidence:string, basis:string, drivers:array<int, string>, note:string}
     */
    private function byPortal(Company $company): array
    {
        $domains = $company->relationLoaded('domains')
            ? $company->domains
            : $company->domains()->get();

        if ($domains->isEmpty()) {
            return $this->none('ドメイン未登録のため自動提案なし。');
        }

        $own = $domains->where('is_portal', false)->count();
        $portal = $domains->where('is_portal', true)->count();

        if ($own > 0 && $portal === 0) {
            return [
                'value' => 1,
                'confidence' => '0.6',
                'basis' => 'auto',
                'drivers' => ['own_domain'],
                'note' => '自社ドメインのみ登録。ポータル/SNS依存は低めに見積もる。',
            ];
        }

        if ($portal > 0 && $own === 0) {
            return [
                'value' => 5,
                'confidence' => '0.6',
                'basis' => 'auto',
                'drivers' => ['portal_only'],
                'note' => '登録ドメインがポータル/SNSのみ。自社HPより外部依存が強い可能性。',
            ];
        }

        return [
            'value' => 3,
            'confidence' => '0.6',
            'basis' => 'auto',
            'drivers' => ['mixed'],
            'note' => '自社ドメインとポータル/SNSが併存。中間値として提案。',
        ];
    }

    /**
     * @return array{value:int|null, confidence:string, basis:string, drivers:array<int, string>, note:string}
     */
    private function none(string $note): array
    {
        return [
            'value' => null,
            'confidence' => '0.3',
            'basis' => 'auto',
            'drivers' => [],
            'note' => $note,
        ];
    }
}
