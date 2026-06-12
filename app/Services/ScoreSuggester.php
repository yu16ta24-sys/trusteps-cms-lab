<?php

namespace App\Services;

use App\Models\Company;

class ScoreSuggester
{
    public const ALGO = 'suggest_v2';

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
        $hpFact = $this->getLatestHpFact($company);
        $hasHp  = $hpFact !== null && !($hpFact->hp_js_rendering_required ?? false);

        $industrySig       = $this->loadIndustrySignals($company);
        $hasIndustrySig    = (bool) ($industrySig['_has_industry_scores'] ?? false);
        unset($industrySig['_has_industry_scores']);

        $hpSub   = $this->computeHpSubscores($hasHp ? $hpFact : null);
        $signals = array_merge($industrySig, $hpSub);

        $axes = $this->composeAxesV2($signals, $hasHp);

        $totalScore = round(
            array_sum(array_column($axes, 'score')) / count($axes),
            2
        );

        $flags       = $this->buildFlagsV2($company, $hasHp, $hpFact);
        $capsApplied = [];

        if (in_array('kill_flag_active', $flags, true) && $totalScore > 2.0) {
            $totalScore    = 2.0;
            $capsApplied[] = 'kill_flag_cap';
        }

        $rank          = $this->rankFromTotal($totalScore);
        $candidateType = $this->candidateTypeV2($axes, $flags, $totalScore, $hasHp, $hasIndustrySig);
        $confidence    = $this->overallConfidence($hasHp, $hasIndustrySig);

        return [
            'score_version'  => 'scoring_v1.0',
            'axes'           => $axes,
            'total_score'    => $totalScore,
            'rank'           => $rank,
            'candidate_type' => $candidateType,
            'confidence'     => $confidence,
            'flags'          => $flags,
            'caps_applied'   => $capsApplied,
            'reason_summary' => $this->buildReasonSummaryV2($axes, $totalScore, $rank, $candidateType, $flags),
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
    private function computeHpSubscores(?object $hpFact): array
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

        // conversion_gap_score: デジタル問い合わせ導線の欠如度
        $cg = 0.0;
        if (!$hpFact->has_contact_form) $cg += 1.5;
        if (!$hpFact->has_public_email) $cg += 1.5;
        if (!$hpFact->has_phone)        $cg += 1.0;
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
        $cr = 0.0;
        if ($hpFact->hp_contact_email)    $cr += 2.0;
        if ($hpFact->hp_contact_form_url) $cr += 2.0;
        if ($hpFact->hp_contact_phone)    $cr += 1.0;
        $contactRoute = min(5.0, $cr);

        // function_complexity_score / low_function_complexity_score
        $fc = 0.0;
        if ($hpFact->has_ec)          $fc += 2.5;
        if ($hpFact->has_reservation) $fc += 2.5;
        $funcComplexity    = min(5.0, $fc);
        $lowFuncComplexity = 6.0 - $funcComplexity; // spec: 6 - value（1〜6）

        return array_merge($defaults, [
            'site_weakness_score'           => $siteWeakness,
            'conversion_gap_score'          => $conversionGap,
            'freshness_gap_score'           => $freshnessGap,
            'contact_route_score'           => $contactRoute,
            'function_complexity_score'     => $funcComplexity,
            'low_function_complexity_score' => $lowFuncComplexity,
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

    /**
     * 全シグナルを 5 軸スコアに合成する。
     * 各軸は構成サブスコアの単純平均。
     */
    private function composeAxesV2(array $s, bool $hasHp): array
    {
        $g   = fn(string $k) => (float) ($s[$k] ?? 3.0);
        $avg = fn(array $sub) => round(array_sum($sub) / count($sub), 2);

        // opportunity_score: HP改善機会の大きさ
        $oppSub = [
            'site_weakness_score'      => $g('site_weakness_score'),
            'freshness_gap_score'      => $g('freshness_gap_score'),
            'improvement_room_score'   => $g('improvement_room_score'),
            'update_room_score'        => $g('update_room_score'),
            'market_white_space_score' => $g('market_white_space_score'),
        ];

        // impact_score: 成約したときのビジネスインパクト
        $impSub = [
            'trust_hp_importance_score'     => $g('trust_hp_importance_score'),
            'conversion_gap_score'          => $g('conversion_gap_score'),
            'conversion_value_score'        => $g('conversion_value_score'),
            'customer_research_depth_score' => $g('customer_research_depth_score'),
        ];

        // feasibility_score: 実装・提案の容易さ
        $feasSub = [
            'low_function_complexity_score' => $g('low_function_complexity_score'),
            'wp_white_space_score'          => $g('wp_white_space_score'),
            'addon_fit_score'               => $g('addon_fit_score'),
            'low_regulation_risk_score'     => $g('low_regulation_risk_score'),
            'migration_ease_score'          => $g('migration_ease_score'),
        ];

        // reachability_score: 営業到達性
        $reachSub = [
            'sales_reachability_score'  => $g('sales_reachability_score'),
            'contact_route_score'       => $g('contact_route_score'),
            'portal_independence_score' => $g('portal_independence_score'),
        ];

        // recurring_score: 継続・自走運用の余地
        $recSub = [
            'self_update_fit_score'             => $g('self_update_fit_score'),
            'update_neta_score'                 => $g('update_neta_score'),
            'update_literacy_score'             => $g('update_literacy_score'),
            'recurring_content_potential_score' => $g('recurring_content_potential_score'),
        ];

        return [
            'opportunity_score' => [
                'score'       => $avg($oppSub),
                'confidence'  => $hasHp ? 0.55 : 0.35,
                'reason_json' => ['subscores' => $oppSub],
            ],
            'impact_score' => [
                'score'       => $avg($impSub),
                'confidence'  => $hasHp ? 0.50 : 0.35,
                'reason_json' => ['subscores' => $impSub],
            ],
            'feasibility_score' => [
                'score'       => $avg($feasSub),
                'confidence'  => $hasHp ? 0.50 : 0.35,
                'reason_json' => ['subscores' => $feasSub],
            ],
            'reachability_score' => [
                'score'       => $avg($reachSub),
                'confidence'  => $hasHp ? 0.60 : 0.30,
                'reason_json' => ['subscores' => $reachSub],
            ],
            'recurring_score' => [
                'score'       => $avg($recSub),
                'confidence'  => 0.40,
                'reason_json' => ['subscores' => $recSub],
            ],
        ];
    }

    private function rankFromTotal(float $total): string
    {
        return match (true) {
            $total >= 4.0 => 'A',
            $total >= 3.0 => 'B',
            $total >= 2.0 => 'C',
            default       => 'D',
        };
    }

    private function candidateTypeV2(array $axes, array $flags, float $total, bool $hasHp, bool $hasIndustrySig): string
    {
        if (!$hasHp && !$hasIndustrySig) {
            return 'pending_analysis';
        }
        if ($total < 2.5) {
            return 'low_priority';
        }

        $opp   = $axes['opportunity_score']['score']   ?? 3.0;
        $imp   = $axes['impact_score']['score']        ?? 3.0;
        $feas  = $axes['feasibility_score']['score']   ?? 3.0;
        $reach = $axes['reachability_score']['score']  ?? 3.0;

        if ($feas >= 4.0 && $reach >= 3.5 && $opp >= 3.0) {
            return 'quick_win';
        }
        if ($imp >= 3.5 && $total >= 3.0 && $feas < 3.0) {
            return 'strategic';
        }
        return 'renewal_candidate';
    }

    private function buildFlagsV2(Company $company, bool $hasHp, ?object $hpFact): array
    {
        $flags = [];

        if ($company->is_killed ||
            ($company->relationLoaded('killFlags') && $company->killFlags->isNotEmpty())) {
            $flags[] = 'kill_flag_active';
        }
        if (!$company->primaryDomain) {
            $flags[] = 'no_primary_domain';
        }
        if (!$hasHp) {
            $flags[] = 'no_hp_analysis';
        }
        if ($hpFact && ($hpFact->hp_js_rendering_required ?? false)) {
            $flags[] = 'js_site';
        }

        return $flags;
    }

    private function overallConfidence(bool $hasHp, bool $hasIndustrySig): float
    {
        $c = 0.3;
        if ($hasHp)         $c += 0.2;
        if ($hasIndustrySig) $c += 0.1;
        return round($c, 2);
    }

    private function buildReasonSummaryV2(array $axes, float $total, string $rank, string $type, array $flags): string
    {
        $typeLabel = [
            'renewal_candidate' => '更新リニューアル候補',
            'quick_win'         => 'クイックウィン候補',
            'strategic'         => '戦略案件候補',
            'low_priority'      => '優先度低',
            'pending_analysis'  => '要解析',
        ][$type] ?? $type;

        $axisLabels = [
            'opportunity_score'  => '機会',
            'impact_score'       => 'インパクト',
            'feasibility_score'  => '実行可能性',
            'reachability_score' => '営業到達性',
            'recurring_score'    => '継続性',
        ];

        $parts = ["総合{$total}点（{$rank}ランク / {$typeLabel}）。"];
        foreach ($axes as $key => $ax) {
            $label   = $axisLabels[$key] ?? $key;
            $parts[] = "{$label}:{$ax['score']}";
        }
        if (!empty($flags)) {
            $parts[] = 'フラグ:' . implode(',', $flags);
        }

        return implode(' ', $parts);
    }
}
