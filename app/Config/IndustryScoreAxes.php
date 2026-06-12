<?php

namespace App\Config;

class IndustryScoreAxes
{
    /**
     * 12軸の定義。
     * direction: higher_is_better / higher_is_worse / dual
     * normalized_key: 正規化後のシグナル名
     * dual_normalized_key: dual 方向の第二シグナル（raw 値をそのまま使う側）
     */
    public const AXES = [
        'update_neta_score' => [
            'label'          => '更新ネタ発生度',
            'direction'      => 'higher_is_better',
            'normalized_key' => 'update_neta_score',
        ],
        'self_update_fit_score' => [
            'label'          => '自走更新との相性',
            'direction'      => 'higher_is_better',
            'normalized_key' => 'self_update_fit_score',
        ],
        'addon_fit_score' => [
            'label'          => '業種アドオン適性',
            'direction'      => 'higher_is_better',
            'normalized_key' => 'addon_fit_score',
        ],
        'trust_hp_importance_score' => [
            'label'          => '公式HPの信用重要度',
            'direction'      => 'higher_is_better',
            'normalized_key' => 'trust_hp_importance_score',
        ],
        'existing_hp_maturity_score' => [
            'label'          => '既存HP成熟度',
            'direction'      => 'higher_is_worse',
            'normalized_key' => 'improvement_room_score',   // 6 - value
        ],
        'wp_penetration_score' => [
            'label'          => 'WordPress普及度',
            'direction'      => 'higher_is_worse',
            'normalized_key' => 'wp_white_space_score',     // 6 - value
        ],
        'active_update_rate_score' => [
            'label'               => '更新運用成立度',
            'direction'           => 'dual',
            'normalized_key'      => 'update_room_score',       // 6 - value（改善余地）
            'dual_normalized_key' => 'update_literacy_score',   // value（更新リテラシー）
        ],
        'market_white_space_score' => [
            'label'          => '市場余白',
            'direction'      => 'higher_is_better',
            'normalized_key' => 'market_white_space_score',
        ],
        'sales_reachability_score' => [
            'label'          => '営業到達性',
            'direction'      => 'higher_is_better',
            'normalized_key' => 'sales_reachability_score',
        ],
        'portal_dependency_score' => [
            'label'          => 'ポータル依存度',
            'direction'      => 'higher_is_worse',
            'normalized_key' => 'portal_independence_score', // 6 - value
        ],
        'customer_research_depth_score' => [
            'label'          => '顧客調査深度',
            'direction'      => 'higher_is_better',
            'normalized_key' => 'customer_research_depth_score',
        ],
        'conversion_value_score' => [
            'label'          => '転換価値',
            'direction'      => 'higher_is_better',
            'normalized_key' => 'conversion_value_score',
        ],
    ];

    /**
     * UI上でバッジ色を反転表示すべき axis_key。
     * higher_is_worse + dual（主方向が反転）を含む。
     */
    public const INVERTED_DISPLAY_KEYS = [
        'existing_hp_maturity_score',
        'wp_penetration_score',
        'active_update_rate_score',
        'portal_dependency_score',
    ];

    public static function direction(string $axisKey): string
    {
        return self::AXES[$axisKey]['direction'] ?? 'higher_is_better';
    }

    public static function isInvertedForDisplay(string $axisKey): bool
    {
        return in_array($axisKey, self::INVERTED_DISPLAY_KEYS, true);
    }
}
