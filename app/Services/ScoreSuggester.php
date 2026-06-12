<?php

namespace App\Services;

use App\Models\Company;

class ScoreSuggester
{
    public const ALGO = 'suggest_v2';

    /** rank=D へ強制除外する critical kill flags */
    private const CRITICAL_FLAGS = [
        'already_strong_site',
        'portal_only_business',
        'requires_ec_core',
        'requires_booking_core',
        'requires_member_system_core',
        'requires_inventory_core',
        'requires_external_system_core',
        'enterprise_decision',
        'no_contact_route',
    ];

    /** reason_json / negative 用のフラグ日本語ラベル */
    private const FLAG_LABELS = [
        'already_strong_site'           => '既に十分なHPがある',
        'portal_only_business'          => 'ポータル集客中心のビジネス',
        'requires_ec_core'              => 'EC構築が中核で難易度が高い',
        'requires_booking_core'         => '予約システムが中核で難易度が高い',
        'requires_member_system_core'   => '会員システムが中核で難易度が高い',
        'requires_inventory_core'       => '在庫管理が中核で難易度が高い',
        'requires_external_system_core' => '外部システム連携が中核で難易度が高い',
        'enterprise_decision'           => '意思決定が大企業型で到達が困難',
        'no_contact_route'              => '連絡経路が確認できない',
        'sns_only_sufficient'           => 'SNSで完結しており公式HP需要が低い',
        'legal_or_medical_high_risk'    => '法務・医療系で表現規制リスクが高い',
        'franchise_or_chain'            => 'FC/チェーンで本部決裁の可能性',
        'no_official_site'              => '公式サイト未確認',
        'no_hp_analysis'                => 'HP解析未実施',
        'js_site'                       => 'JS描画サイトで自動解析不可',
        'rank_a_provisional'            => '高評価だがデータ不足のため要確認',
    ];

    /** サブスコアキー → 日本語ラベル（axis_detail 表示用） */
    private const SUBSCORE_LABELS = [
        'site_weakness_score'               => 'サイト技術的弱点',
        'content_gap_score'                 => 'コンテンツ不足',
        'conversion_gap_score'              => '問い合わせ導線の弱さ',
        'freshness_gap_score'               => '更新停滞度',
        'comparison_material_gap_score'     => '比較材料の不足',
        'improvement_room_score'            => '改善余地',
        'trust_hp_importance_score'         => '公式HP信用重要度',
        'portal_independence_score'         => 'ポータル非依存度',
        'customer_research_depth_score'     => '比較検討の深さ',
        'conversion_value_score'            => '問い合わせ単価価値',
        'market_white_space_score'          => '市場余白',
        'comparison_leverage_score'         => '比較優位の作りやすさ',
        'simple_site_fit_score'             => 'シンプルサイト適合',
        'low_function_complexity_score'     => '機能シンプルさ',
        'addon_fit_score'                   => '追加機能提案余地',
        'low_regulation_risk_score'         => '規制リスクの低さ',
        'migration_ease_score'              => '移行容易性',
        'sales_reachability_score'          => '営業到達性',
        'contact_route_score'               => '連絡経路の豊富さ',
        'decision_distance_score'           => '意思決定者との距離',
        'company_size_fit_score'            => '企業規模適合',
        'local_business_score'              => '地域密着度',
        'update_neta_score'                 => '更新ネタの豊富さ',
        'self_update_fit_score'             => '自走更新適合',
        'update_literacy_score'             => '更新リテラシー',
        'recurring_content_potential_score' => '継続コンテンツ余地',
        'recruit_content_need_score'        => '採用コンテンツ需要',
        'maintenance_need_score'            => '保守需要',
    ];

    private const DEV_DIFFICULTY = [
        'lodging'         => 1,
        'lodging_leisure' => 1,
        'medical'         => 2,
        'food'           => 2,
        'beauty'         => 2,
        'real_estate'    => 2,
        'retail'         => 3,
        'automotive'     => 3,
        'therapy'        => 3,
        'btob_service'   => 4,
        'manufacturing'  => 4,
        'welfare_care'   => 4,
        'child_education' => 4,
        'culture_event'  => 4,
        'local_service'  => 4,
        'agriculture'    => 4,
        'construction'   => 5,
        'exterior_paint' => 5,
        'professional'   => 5,
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

            $valueMap = ['none' => 5, 'low' => 4, 'medium' => 3, 'high' => 1];
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
                'value' => 5, 'confidence' => '0.6', 'basis' => 'auto',
                'drivers' => ['own_domain'],
                'note'    => '自社ドメインのみ登録。自社HP自立度は高めに見積もる。',
            ];
        }

        if ($portal > 0 && $own === 0) {
            return [
                'value' => 1, 'confidence' => '0.6', 'basis' => 'auto',
                'drivers' => ['portal_only'],
                'note'    => '登録ドメインがポータル/SNSのみ。自社HPがなく自立度は低い。',
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

    // =========================================================
    // === suggestV2 (scoring_v1.0) ============================
    // =========================================================

    /**
     * 5軸スコアリング v1.0 を計算して返す。
     * 既存の suggest() は変更しない。
     *
     * @return array{score_version:string, axes:array, total_score:float, rank:string,
     *               candidate_type:string, confidence:float, flags:array, caps_applied:array, reason_summary:string}
     */
    public function suggestV2(Company $company): array
    {
        $company->loadMissing('killFlags', 'industry.parent', 'primaryDomain');

        $hpFact = $this->getLatestHpFact($company);
        $hasHp  = $hpFact !== null && !($hpFact->hp_js_rendering_required ?? false);

        $industrySig    = $this->loadIndustrySignals($company);
        $hasIndustrySig = (bool) ($industrySig['_has_industry_scores'] ?? false);
        unset($industrySig['_has_industry_scores']);

        $hpSub = $this->computeHpSubscores($hasHp ? $hpFact : null, $industrySig);
        $s     = array_merge($industrySig, $hpSub);

        $flags = $this->collectFlagsV2($company, $hasHp, $hpFact);

        $clamp = fn (float $v): float => max(0.0, min(5.0, round($v, 2)));
        $g     = fn (string $k): float => (float) ($s[$k] ?? 3.0);

        // === 5軸算出（加重） ===
        $opportunity = $clamp(
            $g('site_weakness_score') * 0.40
            + $g('content_gap_score') * 0.20
            + $g('conversion_gap_score') * 0.15
            + $g('freshness_gap_score') * 0.10
            + $g('comparison_material_gap_score') * 0.10
            + $g('improvement_room_score') * 0.05
        );

        $comparisonLeverage =
            $g('customer_research_depth_score') * 0.60
            + $g('comparison_material_gap_score') * 0.40;

        $impact = $clamp(
            $g('trust_hp_importance_score') * 0.25
            + $g('portal_independence_score') * 0.20
            + $g('customer_research_depth_score') * 0.20
            + $g('conversion_value_score') * 0.15
            + $g('market_white_space_score') * 0.10
            + $comparisonLeverage * 0.10
        );

        $feasibility = $clamp(
            $g('simple_site_fit_score') * 0.30
            + $g('low_function_complexity_score') * 0.30
            + $g('addon_fit_score') * 0.20
            + $g('low_regulation_risk_score') * 0.10
            + $g('migration_ease_score') * 0.10
        );

        $reachability = $clamp(
            $g('sales_reachability_score') * 0.25
            + $g('contact_route_score') * 0.25
            + $g('decision_distance_score') * 0.25
            + $g('company_size_fit_score') * 0.15
            + $g('local_business_score') * 0.10
        );

        $recurring = $clamp(
            $g('update_neta_score') * 0.20
            + $g('self_update_fit_score') * 0.15
            + $g('update_literacy_score') * 0.10
            + $g('recurring_content_potential_score') * 0.20
            + $g('recruit_content_need_score') * 0.15
            + $g('maintenance_need_score') * 0.20
        );

        // === kill_flags補正（軸キャップ） ===
        $capsApplied = [];
        $capMap = [
            'already_strong_site'           => ['opportunity',  2.0],
            'portal_only_business'          => ['impact',       2.0],
            'requires_ec_core'              => ['feasibility',  2.0],
            'requires_booking_core'         => ['feasibility',  2.0],
            'requires_member_system_core'   => ['feasibility',  2.0],
            'requires_inventory_core'       => ['feasibility',  2.0],
            'requires_external_system_core' => ['feasibility',  2.0],
            'enterprise_decision'           => ['reachability', 2.0],
            'no_contact_route'              => ['reachability', 1.5],
            'sns_only_sufficient'           => ['impact',       3.0],
            'legal_or_medical_high_risk'    => ['feasibility',  3.0],
            'franchise_or_chain'            => ['reachability', 2.5],
        ];
        foreach ($flags as $flag) {
            if (!isset($capMap[$flag])) {
                continue;
            }
            [$axisName, $cap] = $capMap[$flag];
            $applied = false;
            switch ($axisName) {
                case 'opportunity':  if ($opportunity  > $cap) { $opportunity  = $cap; $applied = true; } break;
                case 'impact':       if ($impact       > $cap) { $impact       = $cap; $applied = true; } break;
                case 'feasibility':  if ($feasibility  > $cap) { $feasibility  = $cap; $applied = true; } break;
                case 'reachability': if ($reachability > $cap) { $reachability = $cap; $applied = true; } break;
            }
            if ($applied) {
                $capsApplied[] = ['flag' => $flag, 'axis' => $axisName . '_score', 'cap' => $cap];
            }
        }

        // === total_score（加重平均） ===
        $total = $opportunity * 0.20
            + $impact * 0.30
            + $feasibility * 0.20
            + $reachability * 0.15
            + $recurring * 0.15;

        // === ゲート補正 ===
        if ($impact < 2.5)       { $total = min($total, 3.2); $capsApplied[] = ['gate' => 'impact_lt_2.5',       'cap' => 3.2]; }
        if ($feasibility < 2.5)  { $total = min($total, 3.0); $capsApplied[] = ['gate' => 'feasibility_lt_2.5',  'cap' => 3.0]; }
        if ($reachability < 2.0) { $total = min($total, 2.8); $capsApplied[] = ['gate' => 'reachability_lt_2.0', 'cap' => 2.8]; }
        if ($opportunity < 2.0)  { $total = min($total, 3.0); $capsApplied[] = ['gate' => 'opportunity_lt_2.0',  'cap' => 3.0]; }
        $total = round($total, 2);

        // === confidence ===
        $confidence = $this->computeConfidenceV2($company, $hpFact, $hasIndustrySig, $s);

        // === rank判定 ===
        $hasCritical = count(array_intersect($flags, self::CRITICAL_FLAGS)) > 0;
        if ($hasCritical || $total < 2.8) {
            $rank = 'D';
        } elseif ($total >= 4.2) {
            $rank = 'A';
            if ($confidence < 0.70) {
                $flags[] = 'rank_a_provisional'; // A候補（確定でなく要確認）
            }
        } elseif ($total >= 3.5) {
            $rank = 'B';
        } else {
            $rank = 'C';
        }

        // === candidate_type判定 ===
        $candidateType = $this->candidateTypeV2(
            $opportunity, $impact, $feasibility, $reachability, $recurring, $flags
        );

        // === reason生成 ===
        $reason = $this->buildReasonV2(
            $s,
            compact('opportunity', 'impact', 'feasibility', 'reachability', 'recurring'),
            $confidence,
            $flags
        );
        $reason['evidence'] = [
            'industry_key'        => $company->industry?->slug,
            'has_industry_scores' => $hasIndustrySig,
            'normalized'          => [
                'site_weakness'           => round($g('site_weakness_score'), 1),
                'trust_hp_importance'     => round($g('trust_hp_importance_score'), 1),
                'portal_independence'     => round($g('portal_independence_score'), 1),
                'customer_research_depth' => round($g('customer_research_depth_score'), 1),
                'conversion_value'        => round($g('conversion_value_score'), 1),
                'contact_route'           => round($g('contact_route_score'), 1),
                'simple_site_fit'         => round($g('simple_site_fit_score'), 1),
                'sales_reachability'      => round($g('sales_reachability_score'), 1),
                'company_size_fit'        => round($g('company_size_fit_score'), 1),
            ],
            'site_analysis' => [
                'has_hp'    => $hasHp,
                'has_form'  => $hpFact?->hp_contact_form_url !== null,
                'has_email' => $hpFact?->hp_contact_email !== null,
                'has_phone' => $hpFact?->hp_contact_phone !== null,
            ],
        ];

        $axes = [
            'opportunity_score' => [
                'score' => $opportunity, 'confidence' => $confidence,
                'reason_json' => ['signals' => [
                    'site_weakness_score'           => $g('site_weakness_score'),
                    'content_gap_score'             => $g('content_gap_score'),
                    'conversion_gap_score'          => $g('conversion_gap_score'),
                    'freshness_gap_score'           => $g('freshness_gap_score'),
                    'comparison_material_gap_score' => $g('comparison_material_gap_score'),
                    'improvement_room_score'        => $g('improvement_room_score'),
                ]],
            ],
            'impact_score' => [
                'score' => $impact, 'confidence' => $confidence,
                'reason_json' => ['signals' => [
                    'trust_hp_importance_score'     => $g('trust_hp_importance_score'),
                    'portal_independence_score'     => $g('portal_independence_score'),
                    'customer_research_depth_score' => $g('customer_research_depth_score'),
                    'conversion_value_score'        => $g('conversion_value_score'),
                    'market_white_space_score'      => $g('market_white_space_score'),
                    'comparison_leverage_score'     => round($comparisonLeverage, 2),
                ]],
            ],
            'feasibility_score' => [
                'score' => $feasibility, 'confidence' => $confidence,
                'reason_json' => ['signals' => [
                    'simple_site_fit_score'         => $g('simple_site_fit_score'),
                    'low_function_complexity_score' => $g('low_function_complexity_score'),
                    'addon_fit_score'               => $g('addon_fit_score'),
                    'low_regulation_risk_score'     => $g('low_regulation_risk_score'),
                    'migration_ease_score'          => $g('migration_ease_score'),
                ]],
            ],
            'reachability_score' => [
                'score' => $reachability, 'confidence' => $confidence,
                'reason_json' => ['signals' => [
                    'sales_reachability_score' => $g('sales_reachability_score'),
                    'contact_route_score'      => $g('contact_route_score'),
                    'decision_distance_score'  => $g('decision_distance_score'),
                    'company_size_fit_score'   => $g('company_size_fit_score'),
                    'local_business_score'     => $g('local_business_score'),
                ]],
            ],
            'recurring_score' => [
                'score' => $recurring, 'confidence' => $confidence,
                'reason_json' => ['signals' => [
                    'update_neta_score'                 => $g('update_neta_score'),
                    'self_update_fit_score'             => $g('self_update_fit_score'),
                    'update_literacy_score'             => $g('update_literacy_score'),
                    'recurring_content_potential_score' => $g('recurring_content_potential_score'),
                    'recruit_content_need_score'        => $g('recruit_content_need_score'),
                    'maintenance_need_score'            => $g('maintenance_need_score'),
                ]],
            ],
        ];

        // === 各軸の詳細内訳（sub_scores / factors / narrative）を付与 ===
        $axisDetails = $this->buildAxisDetailsV2(
            $s,
            $hasHp ? $hpFact : null,
            $hasHp,
            $comparisonLeverage,
            compact('opportunity', 'impact', 'feasibility', 'reachability', 'recurring'),
            $capsApplied
        );
        foreach ($axisDetails as $axisKey => $detail) {
            $axes[$axisKey]['reason_json']['axis_detail'] = $detail;
        }

        return [
            'score_version'  => 'scoring_v1.0',
            'axes'           => $axes,
            'total_score'    => $total,
            'rank'           => $rank,
            'candidate_type' => $candidateType,
            'confidence'     => $confidence,
            'flags'          => array_values(array_unique($flags)),
            'caps_applied'   => $capsApplied,
            'reason_summary' => $reason['summary'],
            'reason_json'    => $reason,
        ];
    }

    /**
     * 評価対象フラグを収集（人手の kill_flags + 派生フラグ）。
     */
    private function collectFlagsV2(Company $company, bool $hasHp, ?object $hpFact): array
    {
        $flags = $company->killFlags->pluck('flag')->filter()->values()->all();

        if (!$company->primaryDomain) {
            $flags[] = 'no_official_site';
        }
        if (!$hasHp) {
            $flags[] = 'no_hp_analysis';
        }
        if ($hpFact && ($hpFact->hp_js_rendering_required ?? false)) {
            $flags[] = 'js_site';
        }

        return array_values(array_unique($flags));
    }

    /**
     * confidence を算出（最大 1.0）。
     */
    private function computeConfidenceV2(Company $company, ?object $hpFact, bool $hasIndustrySig, array $s): float
    {
        $c = 0.0;

        if ($hpFact !== null) {
            $c += 0.30; // URL解析済み（latestFact存在）
        }
        if ($hasIndustrySig) {
            $c += 0.20; // 業種スコアあり
        }
        if ($hpFact && ($hpFact->hp_contact_email || $hpFact->hp_contact_form_url || $hpFact->hp_contact_phone)) {
            $c += 0.15; // 連絡先確認済み
        }
        if (abs(((float) ($s['company_size_fit_score'] ?? 3.0)) - 3.0) > 0.001) {
            $c += 0.15; // 会社規模推定あり（中立値以外）
        }
        if ($company->industry) {
            $c += 0.10; // 事業内容判定あり
        }
        if ($hpFact && $hpFact->hp_update_staleness_days !== null) {
            $c += 0.10; // 最終更新日推定あり
        }

        return round(min(1.0, $c), 2);
    }

    /**
     * candidate_type を判定（最初に一致したものを返す）。
     */
    private function candidateTypeV2(float $opp, float $imp, float $feas, float $reach, float $rec, array $flags): string
    {
        // 除外条件を最優先
        if ($imp < 2.5 || $feas < 2.5 || $reach < 2.0) {
            return 'reject';
        }
        if (in_array('no_official_site', $flags, true) && $imp >= 3.0 && $reach >= 3.0) {
            return 'new_site_candidate';
        }
        if ($opp >= 3.5 && $imp >= 3.5 && $feas >= 3.0) {
            return 'renewal_candidate';
        }
        if ($feas >= 3.5 && $rec >= 3.5) {
            return 'cms_conversion_candidate';
        }
        if ($rec >= 3.5 && $opp >= 2.5 && $opp <= 4.0) {
            return 'maintenance_candidate';
        }

        return 'unclassified';
    }

    /**
     * reason_json（positive/negative/summary）をルールベースで生成。
     */
    private function buildReasonV2(array $s, array $axes, float $confidence, array $flags): array
    {
        $g = fn (string $k): float => (float) ($s[$k] ?? 3.0);

        // === positive（最大3件） ===
        $positive = [];
        if ($g('portal_independence_score') >= 4)    $positive[] = 'ポータル依存が低い';
        if ($g('trust_hp_importance_score') >= 4)     $positive[] = '公式HPの信用重要度が高い業種';
        if ($g('customer_research_depth_score') >= 4) $positive[] = '比較検討されやすい業種';
        if ($g('conversion_value_score') >= 4)        $positive[] = '問い合わせ1件の価値が高い業種';
        if ($g('site_weakness_score') >= 4)           $positive[] = 'HPに明確な改善余地がある';
        if ($g('contact_route_score') >= 4)           $positive[] = '営業連絡の入口がある';
        if ($axes['recurring'] >= 4)                  $positive[] = '継続更新ネタが豊富';
        $positive = array_slice($positive, 0, 3);

        // === negative（最大3件） ===
        $negative = [];
        if ($g('contact_route_score') <= 2)  $negative[] = '問い合わせ導線が弱い';
        if ($axes['impact'] < 2.5)           $negative[] = 'HP改善の業種効果が低い';
        if ($axes['feasibility'] < 2.5)      $negative[] = '制作難易度が高い可能性';
        foreach ($flags as $flag) {
            if (isset(self::FLAG_LABELS[$flag])) {
                $negative[] = self::FLAG_LABELS[$flag];
            }
        }
        if ($confidence < 0.70) {
            $negative[] = '一部データ未取得のため目視確認推奨';
        }
        $negative = array_slice($negative, 0, 3);

        // === summary（positive上位2 + negative上位1） ===
        $parts = array_slice($positive, 0, 2);
        if (!empty($negative)) {
            $parts[] = '一方で' . $negative[0];
        }
        $summary = empty($parts)
            ? '特筆すべき特徴は検出されませんでした。'
            : implode('。', $parts) . '。';

        return [
            'positive' => $positive,
            'negative' => $negative,
            'summary'  => $summary,
        ];
    }

    /**
     * industry_scores テーブルから業種シグナルを読み込み正規化する。
     * '_has_industry_scores' キーで実績データの有無を返す（呼び元で unset すること）。
     */
    private function loadIndustrySignals(Company $company): array
    {
        $neutral  = 3.0;
        $industry = $company->industry;

        if (!$industry) {
            $sig = $this->defaultIndustrySignals();
            $sig['_has_industry_scores'] = false;
            return $sig;
        }

        $scores = \App\Models\IndustryScore::where('industry_key', $industry->slug)
            ->get()
            ->keyBy('axis_key');

        if ($scores->isEmpty() && $industry->parent) {
            $scores = \App\Models\IndustryScore::where('industry_key', $industry->parent->slug)
                ->get()
                ->keyBy('axis_key');
        }

        $signals = [];
        foreach (\App\Config\IndustryScoreAxes::AXES as $axisKey => $meta) {
            $raw = (float) ($scores->get($axisKey)?->value ?? $neutral);
            $nk  = $meta['normalized_key'];
            $dir = $meta['direction'];

            if ($dir === 'higher_is_worse') {
                $signals[$nk] = 6 - $raw;
            } elseif ($dir === 'dual') {
                $signals[$nk]                      = 6 - $raw;
                $signals[$meta['dual_normalized_key']] = $raw;
            } else {
                $signals[$nk] = $raw;
            }
        }

        $signals['_has_industry_scores'] = $scores->isNotEmpty();
        return $signals;
    }

    /**
     * 業種シグナルが全くない場合のデフォルト（全軸 neutral 3.0）。
     */
    private function defaultIndustrySignals(): array
    {
        $n   = 3.0;
        $out = [];
        foreach (\App\Config\IndustryScoreAxes::AXES as $meta) {
            $out[$meta['normalized_key']] = $n;
            if (isset($meta['dual_normalized_key'])) {
                $out[$meta['dual_normalized_key']] = $n;
            }
        }
        return $out;
    }

    /**
     * HpFact から HP 解析サブスコアを算出する。
     * $hpFact が null の場合は全軸 neutral 3.0 を返す。
     */
    /**
     * 問い合わせ導線（フォーム/別ページのフォームURL/メール）が1つでもあるか。
     * has_contact_form（インライン<form>のみ）だと別ページにフォームがある場合を
     * 取りこぼすため、hp_contact_form_url / メール系も含めて統一判定する。
     */
    private function hasContactRouteFact(?object $hpFact): bool
    {
        if (!$hpFact) {
            return false;
        }

        return $hpFact->has_contact_form
            || !empty($hpFact->hp_contact_form_url)
            || $hpFact->has_public_email
            || !empty($hpFact->hp_contact_email);
    }

    private function computeHpSubscores(?object $hpFact, array $industrySig = []): array
    {
        $n        = 3.0;
        $defaults = $this->placeholderHpSub($n);

        if (!$hpFact) {
            return $defaults;
        }

        // site_weakness_score: 技術的弱点の大きさ
        $w = 0.0;
        if (!$hpFact->ssl_enabled)                                   $w += 1.5;
        if (!$hpFact->mobile_friendly)                               $w += 1.5;
        if ($hpFact->cms_type === 'unknown')                         $w += 1.0;
        if ($hpFact->update_status === 'stale_2y')                   $w += 1.0;
        elseif ($hpFact->update_status === 'stale_1y')               $w += 0.5;
        $siteWeakness = min(5.0, $w);

        // 問い合わせ導線の有無（フォーム/別ページのフォームURL/メール）を統一判定
        $hasContactRoute = $this->hasContactRouteFact($hpFact);
        $hasPhoneOnly    = !$hasContactRoute && $hpFact->has_phone;

        // conversion_gap_score: デジタル問い合わせ導線の欠如度
        $cg = 0.0;
        if (!$hasContactRoute) {
            $cg += $hasPhoneOnly ? 2.0 : 3.0; // 電話のみ→デジタル導線欠如 / 完全になし→最大ギャップ
        }
        if (!$hpFact->has_phone) {
            $cg += 1.0;
        }
        $conversionGap = min(5.0, $cg);

        // freshness_gap_score: 更新停滞度（高いほど改善余地大）
        $days         = $hpFact->hp_update_staleness_days;
        $freshnessGap = match (true) {
            $days === null => 3.0,
            $days > 730    => 5.0,
            $days > 365    => 4.0,
            $days > 90     => 2.0,
            default        => 1.0,
        };

        // contact_route_score: 企業への連絡経路の豊富さ（営業アプローチしやすさ）
        // フォーム（インライン or 別ページURL）/ メールアドレスがあれば高評価
        $cr = 0.0;
        $hasEmailRoute = $hpFact->has_public_email || !empty($hpFact->hp_contact_email);
        $hasFormRoute  = $hpFact->has_contact_form  || !empty($hpFact->hp_contact_form_url);
        if ($hasEmailRoute) $cr += 2.0;
        if ($hasFormRoute)  $cr += 2.0;
        if ($hpFact->has_phone || !empty($hpFact->hp_contact_phone)) $cr += 1.0;
        $contactRoute = min(5.0, $cr);

        // function_complexity_score / low_function_complexity_score
        $fc = 0.0;
        if ($hpFact->has_ec)          $fc += 2.5;
        if ($hpFact->has_reservation) $fc += 2.5;
        $funcComplexity    = min(5.0, $fc);
        $lowFuncComplexity = 6.0 - $funcComplexity; // spec: 6 - value（1〜6）

        // ====== コンテンツ検出ベースのサブスコア（Phase 2-A） ======
        // content_gap_score: コンテンツ不足度（高いほど不足=改善余地大、最大6.5を0〜5へ正規化）
        $cgap = 0.0;
        if (!$hpFact->hp_has_case_studies)   $cgap += 1.5;
        if (!$hpFact->hp_has_pricing)        $cgap += 1.0;
        if (!$hpFact->hp_has_testimonials)   $cgap += 0.8;
        if (!$hpFact->hp_has_faq)            $cgap += 0.5;
        if (!$hpFact->hp_has_staff_intro)    $cgap += 0.7;
        if (!$hpFact->hp_has_service_detail) $cgap += 1.0;
        $contentGap = min(5.0, round($cgap / 6.5 * 5, 1));

        // comparison_material_gap_score: 比較材料不足度（最大5.0を0〜5へ正規化）
        $cmgap = 0.0;
        if (!$hpFact->hp_has_case_studies) $cmgap += 2.0;
        if (!$hpFact->hp_has_pricing)      $cmgap += 1.5;
        if (!$hpFact->hp_has_testimonials) $cmgap += 1.0;
        if (!$hpFact->hp_has_faq)          $cmgap += 0.5;
        $comparisonMaterialGap = min(5.0, round($cmgap / 5.0 * 5, 1));

        // simple_site_fit_score: シンプルサイトで完結するか
        $fit = 3.0;
        if ($hpFact->hp_has_service_detail)  $fit += 0.5;
        if ($hpFact->hp_has_company_profile) $fit += 0.5;
        if (!$hpFact->has_ec && !$hpFact->has_reservation) $fit += 1.0;
        $simpleSiteFit = min(5.0, $fit);

        // recurring_content_potential_score: 継続更新ネタ（最大5.0）
        $pot = 0.0;
        if ($hpFact->hp_has_case_studies) $pot += 1.5;
        if ($hpFact->hp_has_recruit_page) $pot += 1.0;
        if ($hpFact->hp_has_news)         $pot += 1.5;
        if ($hpFact->hp_has_testimonials) $pot += 0.5;
        if ($hpFact->hp_has_staff_intro)  $pot += 0.5;
        $recurringContentPotential = min(5.0, $pot);

        // recruit_content_need_score: 採用コンテンツ需要（業種ベース + HP実態）
        $recruitBase        = (float) ($industrySig['update_neta_score'] ?? 3.0);
        $recruitAdjust      = $hpFact->hp_has_recruit_page ? -0.5 : 1.0; // 既にあれば需要低、なければ高
        $recruitContentNeed = max(1.0, min(5.0, $recruitBase + $recruitAdjust));

        // maintenance_need_score: 保守契約の必要性
        $need = 2.0;
        if ($hpFact->cms_type === 'wordpress') $need += 1.5;
        if ($hpFact->hp_has_news)              $need += 0.5;
        if ($hpFact->hp_has_case_studies)      $need += 0.5;
        if ($hpFact->hp_has_recruit_page)      $need += 0.5;
        $maintenanceNeed = min(5.0, $need);

        // decision_distance_score: 意思決定者との距離（高いほど近い=アプローチ容易）
        $dd = 3.0;
        if ($hpFact->hp_has_owner_name)    $dd += 1.0; // 代表者名あり
        if ($hpFact->hp_has_local_keyword) $dd += 0.5; // 地域密着
        $decisionDistance = min(5.0, $dd);

        // local_business_score: 地域密着度
        $lb = 3.0;
        if ($hpFact->hp_has_local_keyword) $lb += 1.5;
        $localBusiness = min(5.0, $lb);

        return array_merge($defaults, [
            'site_weakness_score'               => $siteWeakness,
            'conversion_gap_score'              => $conversionGap,
            'freshness_gap_score'               => $freshnessGap,
            'contact_route_score'               => $contactRoute,
            'function_complexity_score'         => $funcComplexity,
            'low_function_complexity_score'     => $lowFuncComplexity,
            // コンテンツ検出ベース（Phase 2-A）
            'content_gap_score'                 => $contentGap,
            'comparison_material_gap_score'     => $comparisonMaterialGap,
            'simple_site_fit_score'             => $simpleSiteFit,
            'recurring_content_potential_score' => $recurringContentPotential,
            'recruit_content_need_score'        => $recruitContentNeed,
            'maintenance_need_score'            => $maintenanceNeed,
            'decision_distance_score'           => $decisionDistance,
            'local_business_score'              => $localBusiness,
        ]);
    }

    /** HP 解析サブスコアのプレースホルダー（全 neutral）。 */
    private function placeholderHpSub(float $n): array
    {
        return [
            'site_weakness_score'               => $n,
            'conversion_gap_score'              => $n,
            'freshness_gap_score'               => $n,
            'contact_route_score'               => $n,
            'function_complexity_score'         => $n,
            'low_function_complexity_score'     => $n,
            'content_gap_score'                 => $n,
            'comparison_material_score'         => $n,
            'comparison_material_gap_score'     => $n,
            'decision_distance_score'           => $n,
            'company_size_fit_score'            => $n,
            'local_business_score'              => $n,
            'low_regulation_risk_score'         => $n,
            'migration_ease_score'              => $n,
            'simple_site_fit_score'             => $n,
            'recurring_content_potential_score' => $n,
            'recruit_content_need_score'        => $n,
            'maintenance_need_score'            => $n,
        ];
    }

    // =========================================================
    // === axis_detail (sub_scores / factors / narrative) ======
    // =========================================================

    /**
     * 各軸の詳細内訳を構築する。
     * sub_scores（重みと寄与）/ positive_factors / negative_factors /
     * weighted_total / gate_applied / flag_cap_applied / narrative。
     *
     * @param array        $finalScores ['opportunity'=>..,'impact'=>..,..] ゲート/キャップ後
     * @return array<string, array> axisKey('opportunity_score'等) => detail
     */
    private function buildAxisDetailsV2(
        array $s,
        ?object $hpFact,
        bool $hasHp,
        float $comparisonLeverage,
        array $finalScores,
        array $capsApplied
    ): array {
        $g         = fn (string $k): float => (float) ($s[$k] ?? 3.0);
        $isNeutral = fn (float $v): bool => abs($v - 3.0) < 0.001;

        // suggestV2() の加重式と完全一致させること
        $weightMaps = [
            'opportunity_score' => [
                'site_weakness_score' => 0.40, 'content_gap_score' => 0.20, 'conversion_gap_score' => 0.15,
                'freshness_gap_score' => 0.10, 'comparison_material_gap_score' => 0.10, 'improvement_room_score' => 0.05,
            ],
            'impact_score' => [
                'trust_hp_importance_score' => 0.25, 'portal_independence_score' => 0.20, 'customer_research_depth_score' => 0.20,
                'conversion_value_score' => 0.15, 'market_white_space_score' => 0.10, 'comparison_leverage_score' => 0.10,
            ],
            'feasibility_score' => [
                'simple_site_fit_score' => 0.30, 'low_function_complexity_score' => 0.30, 'addon_fit_score' => 0.20,
                'low_regulation_risk_score' => 0.10, 'migration_ease_score' => 0.10,
            ],
            'reachability_score' => [
                'sales_reachability_score' => 0.25, 'contact_route_score' => 0.25, 'decision_distance_score' => 0.25,
                'company_size_fit_score' => 0.15, 'local_business_score' => 0.10,
            ],
            'recurring_score' => [
                'update_neta_score' => 0.20, 'self_update_fit_score' => 0.15, 'update_literacy_score' => 0.10,
                'recurring_content_potential_score' => 0.20, 'recruit_content_need_score' => 0.15, 'maintenance_need_score' => 0.20,
            ],
        ];

        $result = [];
        foreach ($weightMaps as $axisKey => $weights) {
            $short = str_replace('_score', '', $axisKey);
            $score = (float) ($finalScores[$short] ?? 3.0);

            $subScores = [];
            $weighted  = 0.0;
            foreach ($weights as $sk => $w) {
                $val     = $sk === 'comparison_leverage_score' ? round($comparisonLeverage, 2) : $g($sk);
                $contrib = round($val * $w, 3);
                $weighted += $contrib;
                $subScores[] = [
                    'key'          => str_replace('_score', '', $sk),
                    'label'        => self::SUBSCORE_LABELS[$sk] ?? $sk,
                    'score'        => round($val, 2),
                    'weight'       => $w,
                    'contribution' => $contrib,
                    'is_neutral'   => $isNeutral($val),
                ];
            }

            [$pos, $neg]   = $this->axisFactorsV2($axisKey, $g, $hpFact, $hasHp);
            $neutralLabels = array_values(array_map(
                fn ($r) => $r['label'],
                array_filter($subScores, fn ($r) => $r['is_neutral'])
            ));

            $flagCap = false;
            foreach ($capsApplied as $c) {
                if (($c['axis'] ?? null) === $axisKey && isset($c['flag'])) {
                    $flagCap = true;
                }
            }

            $gateApplied = match ($axisKey) {
                'opportunity_score'  => $score < 2.0,
                'impact_score'       => $score < 2.5,
                'feasibility_score'  => $score < 2.5,
                'reachability_score' => $score < 2.0,
                default              => false,
            };

            $result[$axisKey] = [
                'sub_scores'       => $subScores,
                'positive_factors' => $pos,
                'negative_factors' => $neg,
                'weighted_total'   => round($weighted, 2),
                'gate_applied'     => $gateApplied,
                'flag_cap_applied' => $flagCap,
                'narrative'        => $this->axisNarrativeV2($axisKey, $score, $g, $hpFact, $hasHp, $pos, $neg, $neutralLabels),
            ];
        }

        return $result;
    }

    /**
     * 軸ごとの加点要因 / 減点要因をルールベースで生成する。
     * @return array{0: array<int,array>, 1: array<int,array>} [positive, negative]
     */
    private function axisFactorsV2(string $axisKey, callable $g, ?object $hpFact, bool $hasHp): array
    {
        $pos = [];
        $neg = [];
        $P   = function (string $label, float $c, string $src) use (&$pos): void {
            $pos[] = ['label' => $label, 'contribution' => round($c, 2), 'source' => $src];
        };
        $N   = function (string $label, float $c, string $src) use (&$neg): void {
            $neg[] = ['label' => $label, 'contribution' => round($c, 2), 'source' => $src];
        };
        $hp = ($hasHp && $hpFact) ? $hpFact : null;

        switch ($axisKey) {
            case 'opportunity_score':
                if ($hp) {
                    if (!$hp->ssl_enabled) {
                        $P('SSL未対応', 1.5 * 0.40, 'site_weakness');
                    } else {
                        $N('SSL対応済み（改善余地なし）', -1.5 * 0.40, 'site_weakness');
                    }
                    if (!$hp->mobile_friendly) {
                        $P('スマホ非対応', 1.5 * 0.40, 'site_weakness');
                    } else {
                        $N('スマホ対応済み（改善余地なし）', -1.5 * 0.40, 'site_weakness');
                    }
                    if (in_array($hp->cms_type, ['unknown', 'static', null], true)) {
                        $P('静的HTML/CMS未使用', 1.0 * 0.40, 'site_weakness');
                    }
                    $d = $hp->hp_update_staleness_days;
                    if ($d !== null && $d > 730) {
                        $P("更新{$d}日以上停止", 0.20, 'freshness_gap');
                    } elseif ($d !== null && $d > 365) {
                        $P("更新{$d}日以上停止", 0.10, 'freshness_gap');
                    }
                    if (!$this->hasContactRouteFact($hp)) {
                        $P('問い合わせ導線なし', 3.0 * 0.15, 'conversion_gap');
                    }
                    if ($hp->hp_word_count !== null && $hp->hp_word_count < 300) {
                        $P("情報量が少ない（{$hp->hp_word_count}文字）", 0.20, 'content_gap');
                    }
                    if ($hp->hp_has_news) {
                        $N('お知らせセクションあり', -0.10, 'freshness_gap');
                    }
                }
                break;

            case 'impact_score':
                $ti  = $g('trust_hp_importance_score');
                $tiD = (int) round($ti);
                if ($ti >= 4) {
                    $P("公式HPの信用重要度が高い業種（{$tiD}点）", ($ti - 3) * 0.25, 'trust_hp_importance');
                } elseif ($ti < 3) {
                    $N('HP信用重要度が低い業種', ($ti - 3) * 0.25, 'trust_hp_importance');
                }
                $pi  = $g('portal_independence_score');
                $piD = (int) round($pi);
                if ($pi >= 4) {
                    $P("ポータル依存が低い（portal_independence={$piD}）", ($pi - 3) * 0.20, 'portal_independence');
                } elseif ($pi < 3) {
                    $N('ポータル依存が高い', ($pi - 3) * 0.20, 'portal_independence');
                }
                $crd  = $g('customer_research_depth_score');
                $crdD = (int) round($crd);
                if ($crd >= 4) {
                    $P("比較検討されやすい業種（{$crdD}点）", ($crd - 3) * 0.20, 'customer_research_depth');
                }
                $cv  = $g('conversion_value_score');
                $cvD = (int) round($cv);
                if ($cv >= 4) {
                    $P("問い合わせ1件の価値が高い業種（{$cvD}点）", ($cv - 3) * 0.15, 'conversion_value');
                }
                $mw  = $g('market_white_space_score');
                $mwD = (int) round($mw);
                if ($mw >= 4) {
                    $P("市場余白が大きい業種（{$mwD}点）", ($mw - 3) * 0.10, 'market_white_space');
                }
                break;

            case 'feasibility_score':
                if ($hp) {
                    if (!$hp->has_ec && !$hp->has_reservation) {
                        $P('予約・EC機能不要', 0.30, 'low_function_complexity');
                    }
                    if ($hp->has_ec) {
                        $N('EC機能あり（制作難易度上昇）', -0.30, 'low_function_complexity');
                    }
                    if ($hp->has_reservation) {
                        $N('予約機能あり（制作難易度上昇）', -0.30, 'low_function_complexity');
                    }
                    if ($hp->cms_type === 'wordpress') {
                        $P('WordPress使用（移行しやすい）', 0.20, 'migration_ease');
                    }
                }
                $af = $g('addon_fit_score');
                if ($af >= 4) {
                    $P('追加機能提案しやすい業種', ($af - 3) * 0.20, 'addon_fit');
                }
                break;

            case 'reachability_score':
                $crr = $g('contact_route_score');
                if ($crr >= 4) {
                    $P('メール/フォームで連絡可能', ($crr - 3) * 0.25, 'contact_route');
                } elseif (abs($crr - 1.0) < 0.001) {
                    $N('連絡手段なし', (1 - 3) * 0.25, 'contact_route');
                } elseif ($crr <= 2) {
                    $N('連絡手段が限定的', ($crr - 3) * 0.25, 'contact_route');
                }
                $sr = $g('sales_reachability_score');
                if ($sr >= 4) {
                    $P('営業到達しやすい業種', ($sr - 3) * 0.25, 'sales_reachability');
                }
                break;

            case 'recurring_score':
                $un = $g('update_neta_score');
                if ($un >= 4) {
                    $P('更新ネタが豊富な業種', ($un - 3) * 0.20, 'update_neta');
                } elseif ($un < 3) {
                    $N('更新ネタが少ない業種', ($un - 3) * 0.20, 'update_neta');
                }
                $suf = $g('self_update_fit_score');
                if ($suf >= 4) {
                    $P('自走更新との相性が良い', ($suf - 3) * 0.15, 'self_update_fit');
                }
                $mn = $g('maintenance_need_score');
                if ($mn >= 4) {
                    $P('保守契約提案しやすい', ($mn - 3) * 0.20, 'maintenance_need');
                }
                break;
        }

        return [$pos, $neg];
    }

    /**
     * 軸ごとの判定コメント（narrative）をルールベースで生成する。
     */
    private function axisNarrativeV2(
        string $axisKey,
        float $score,
        callable $g,
        ?object $hpFact,
        bool $hasHp,
        array $pos,
        array $neg,
        array $neutralLabels
    ): string {
        $hp    = ($hasHp && $hpFact) ? $hpFact : null;
        $parts = [];

        // 軸ごとの「目視確認すべき対象」のヒント（中立値の注記に使う）
        $neutralHints = [
            'opportunity_score'  => '施工事例・料金表の有無を目視確認する',
            'impact_score'       => '競合他社のHP水準を目視確認する',
            'feasibility_score'  => '予約・EC等の必要機能の有無を確認する',
            'recurring_score'    => '更新ネタ（事例・採用情報等）の実在を確認する',
        ];

        switch ($axisKey) {
            case 'opportunity_score':
                $pf = array_map(fn ($f) => $f['label'], $pos);
                if (count($pf) >= 2) {
                    $parts[] = 'このHPは' . implode('・', array_slice($pf, 0, 3)) . 'の点で明確な弱点があり、リニューアル提案の根拠として使いやすい状態です。';
                } elseif (count($pf) === 1) {
                    $parts[] = "このHPは{$pf[0]}という弱点があり、改善提案の入口になります。";
                } else {
                    $parts[] = 'このHPに大きな技術的弱点は検出されませんでした。技術面の改善余地は小さいため、コンテンツ追加や運用改善を軸にした提案が有効です。';
                }
                if (in_array('問い合わせ導線なし', $pf, true) || $g('conversion_gap_score') >= 4) {
                    $parts[] = '特に問い合わせ導線が弱く、改善後に問い合わせ数が増加しやすい構造です。';
                }
                if (count($pf) >= 2) {
                    $parts[] = '既存HPをベースにした改善提案より、新規構築の方が費用対効果を説明しやすいケースです。';
                } elseif (count($pf) === 1) {
                    $parts[] = '現状のHPを活かしつつ、部分改善から入る提案も可能です。';
                }
                break;

            case 'impact_score':
                $ti  = $g('trust_hp_importance_score');
                $pi  = $g('portal_independence_score');
                $crd = $g('customer_research_depth_score');
                $cv  = $g('conversion_value_score');
                if ($ti >= 4) {
                    $parts[] = 'HP改善が直接集客・信用向上につながる業種構造です。';
                } elseif ($ti < 3) {
                    $parts[] = 'この業種は公式HPの信用寄与が小さめで、HP改善の効果は限定的な可能性があります。';
                } else {
                    $parts[] = 'この業種ではHP改善のインパクトは中程度です。';
                }
                if ($crd >= 4) {
                    $parts[] = '顧客が比較検討する業種のため、HP上の情報充実が意思決定に直結します。';
                }
                if ($pi >= 4) {
                    $parts[] = 'ポータル依存が低く、自社HPが主な集客窓口になり得るため、HP改善の費用対効果を説明しやすい案件です。';
                } elseif ($pi < 3) {
                    $parts[] = '一方でポータル依存が高く、自社HP単独での集客効果は限定的な可能性があります。';
                }
                if ($cv >= 4) {
                    $parts[] = '問い合わせ1件の単価が高い業種のため、成約1件で制作費用を十分回収できる構造です。';
                }
                break;

            case 'feasibility_score':
                $complex = $hp && ($hp->has_ec || $hp->has_reservation);
                if (!$complex) {
                    $parts[] = '会社案内・実績紹介・お知らせ・採用・問い合わせフォームで完結する案件構造で、YUTAさんが得意とする領域です。';
                } else {
                    $parts[] = '予約・EC等の機能が絡む可能性があり、スコープの事前確認が必要な案件です。';
                }
                if ($hp && $hp->cms_type === 'wordpress') {
                    $parts[] = 'WordPressを使用しているため既存コンテンツの移行がしやすく、制作工数を抑えられる可能性があります。';
                }
                if (!$complex) {
                    $parts[] = '予約・EC・会員機能等の複雑な機能が中核でないため、スコープが明確で見積もりしやすい案件です。';
                } else {
                    $parts[] = '機能要件次第で工数が大きく変わるため、初回ヒアリングで要件の核を確認することを推奨します。';
                }
                if ($g('addon_fit_score') >= 4) {
                    $parts[] = '追加機能の提案余地もあり、段階的な機能拡張で継続的に関わりやすい構造です。';
                }
                break;

            case 'reachability_score':
                $crr = $g('contact_route_score');
                $sr  = $g('sales_reachability_score');
                if ($crr >= 4) {
                    $parts[] = 'メール/フォームで直接アプローチ可能で、営業の入口が確保されています。';
                } elseif ($crr <= 2) {
                    $parts[] = '連絡手段が限定的なため、営業の入口確保に別途工夫が必要な案件です。';
                } else {
                    $parts[] = '連絡手段は一定あり、アプローチ可能です。';
                }
                if ($sr >= 4) {
                    $parts[] = '業種的に意思決定者に直接届きやすい構造です。';
                }
                if (in_array('意思決定者との距離', $neutralLabels, true)) {
                    $parts[] = 'ただし意思決定者との距離は目視確認推奨です。HP上に代表者名・担当者名が記載されているか確認すると、アプローチ精度が上がります。';
                }
                break;

            case 'recurring_score':
                $un  = $g('update_neta_score');
                $suf = $g('self_update_fit_score');
                if ($score >= 4 || $un >= 4) {
                    $parts[] = '施工事例・採用情報・お知らせ等の継続更新ネタが見込める業種です。';
                    $parts[] = '月次更新・写真追加・採用ページ運用など、保守・更新契約につなげやすい案件構造です。';
                    $parts[] = '単発制作で終わらず、月額の保守契約として提案できる可能性が高い業種です。';
                    if ($suf >= 4) {
                        $parts[] = '更新代行・投稿代行サービスとの相性も良く、長期継続受注が見込めます。';
                    }
                } elseif ($un < 3) {
                    $parts[] = '更新ネタが少なく、単発制作で終わる可能性があります。';
                    $parts[] = '保守提案時は、更新ネタの掘り起こしやセキュリティ・表示速度の維持など、運用価値の訴求に工夫が必要です。';
                } else {
                    $parts[] = '継続更新の余地は中程度です。お知らせ・実績の更新を軸に、無理のない保守プランから提案するのが現実的です。';
                }
                break;
        }

        if (!empty($neutralLabels) && isset($neutralHints[$axisKey])) {
            $parts[] = '※' . implode('・', array_slice($neutralLabels, 0, 3)) . 'は未取得のため中立値（3.0）。' . $neutralHints[$axisKey] . 'とスコアが変動する可能性があります。';
        }

        return implode('', $parts);
    }

}
