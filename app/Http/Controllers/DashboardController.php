<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyScoreSummary;
use App\Models\HpFact;
use App\Models\SourceRecord;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** company_score_summaries に書き込まれているスコアバージョン（5軸 suggest_v2 由来）。 */
    private const SCORE_VERSION = 'scoring_v1.0';

    public function index(): View
    {
        // ===== company化待ち（未リンク source_records） =====
        $unlinkedSourceCount = SourceRecord::query()
            ->doesntHave('sourceLink')
            ->where('is_excluded', false)
            ->count();

        $nextSourceRecords = SourceRecord::query()
            ->doesntHave('sourceLink')
            ->where('is_excluded', false)
            ->orderBy('id')
            ->limit(5)
            ->get();

        // ===== active company のIDセット =====
        $activeIds = Company::query()
            ->where('is_killed', false)
            ->where('status', '!=', 'merged')
            ->pluck('id');

        // ===== HP解析の有無（hp_facts 経由） =====
        // 解析済み = primaryDomain に紐づく hp_snapshot に extracted_at 付き hp_fact が1件以上
        $analyzedDomainIds = HpFact::query()
            ->join('hp_snapshots', 'hp_facts.hp_snapshot_id', '=', 'hp_snapshots.id')
            ->whereNotNull('hp_facts.extracted_at')
            ->pluck('hp_snapshots.domain_id')
            ->unique()
            ->all();

        $unanalyzedBase = Company::query()
            ->whereIn('id', $activeIds)
            ->whereNotNull('primary_domain_id')
            ->whereHas('primaryDomain', fn ($q) => $q->whereNotIn('id', $analyzedDomainIds));

        $unanalyzedCount = (clone $unanalyzedBase)->count();

        $hpAnalysisQueue = (clone $unanalyzedBase)
            ->with(['industry', 'municipality.prefecture', 'primaryDomain'])
            ->orderBy('id')
            ->limit(5)
            ->get();

        // ===== 5軸スコア（company_score_summaries）集計 =====
        $rankCounts = CompanyScoreSummary::query()
            ->where('score_version', self::SCORE_VERSION)
            ->whereIn('company_id', $activeIds)
            ->selectRaw('`rank`, COUNT(*) as cnt')
            ->groupByRaw('`rank`')
            ->pluck('cnt', 'rank');

        $rankSummary = [
            'S' => (int) ($rankCounts['S'] ?? 0),
            'A' => (int) ($rankCounts['A'] ?? 0),
            'B' => (int) ($rankCounts['B'] ?? 0),
            'C' => (int) ($rankCounts['C'] ?? 0),
            'D' => (int) ($rankCounts['D'] ?? 0),
        ];
        $topRankCount = $rankSummary['S'] + $rankSummary['A'];

        $rankALowConf = CompanyScoreSummary::query()
            ->where('score_version', self::SCORE_VERSION)
            ->whereIn('company_id', $activeIds)
            ->where('rank', 'A')
            ->where('confidence', '<', 0.70)
            ->count();

        $typeCounts = CompanyScoreSummary::query()
            ->where('score_version', self::SCORE_VERSION)
            ->whereIn('company_id', $activeIds)
            ->selectRaw('candidate_type, COUNT(*) as cnt')
            ->groupBy('candidate_type')
            ->pluck('cnt', 'candidate_type');

        $typeSummary = [
            'renewal_candidate'        => (int) ($typeCounts['renewal_candidate'] ?? 0),
            'cms_conversion_candidate' => (int) ($typeCounts['cms_conversion_candidate'] ?? 0),
            'maintenance_candidate'    => (int) ($typeCounts['maintenance_candidate'] ?? 0),
            'new_site_candidate'       => (int) ($typeCounts['new_site_candidate'] ?? 0),
            'reject'                   => (int) ($typeCounts['reject'] ?? 0),
            'unclassified'             => (int) ($typeCounts['unclassified'] ?? 0),
        ];

        // ===== 手動候補 =====
        $manualCount = Company::query()
            ->whereIn('id', $activeIds)
            ->where('is_manual_candidate', true)
            ->count();

        // ===== 営業優先候補 TOP5（rank S/A/B → total_score 降順） =====
        $rankOrder = ['S' => 0, 'A' => 1, 'B' => 2, 'C' => 3, 'D' => 4];
        $priorityQueue = CompanyScoreSummary::query()
            ->where('score_version', self::SCORE_VERSION)
            ->whereIn('company_id', $activeIds)
            ->whereIn('rank', ['S', 'A', 'B'])
            ->with(['company.industry', 'company.municipality.prefecture'])
            ->get()
            ->sort(function ($a, $b) use ($rankOrder) {
                $ra = $rankOrder[$a->rank] ?? 9;
                $rb = $rankOrder[$b->rank] ?? 9;
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }
                return ($b->total_score ?? 0) <=> ($a->total_score ?? 0);
            })
            ->filter(fn ($s) => $s->company !== null)
            ->take(5)
            ->values();

        $summary = [
            'source_records'  => ['unlinked' => $unlinkedSourceCount],
            'companies'       => ['active' => $activeIds->count()],
            'unanalyzed'      => $unanalyzedCount,
            'top_rank'        => $topRankCount,
            'manual'          => $manualCount,
            'ranks'           => $rankSummary,
            'rank_a_low_conf' => $rankALowConf,
            'types'           => $typeSummary,
        ];

        $workBoard = [
            'next_source_records' => $nextSourceRecords,
            'hp_analysis_queue'   => $hpAnalysisQueue,
            'priority_queue'      => $priorityQueue,
        ];

        return view('dashboard', compact('summary', 'workBoard'));
    }
}
