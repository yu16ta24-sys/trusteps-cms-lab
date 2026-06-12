<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyScoreSummary;
use App\Models\SourceRecord;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $scoreAxes = ['hp_weakness', 'self_update_fit', 'dev_difficulty', 'portal_dependence'];

        $companies = Company::query()
            ->with(['scores' => fn ($query) => $query->where('algo_version', 'v1')])
            ->get();

        $activeCompanies = $companies
            ->filter(fn (Company $company) => !$company->is_killed && $company->status !== 'merged');

        $scoreSummary = [
            'unscored' => 0,
            'partial' => 0,
            'fully_scored' => 0,
            'has_auto_suggestion' => 0,
            'manual_adjusted' => 0,
            'suggestion_as_is' => 0,
        ];

        $candidateSummary = [
            'total' => $activeCompanies->count(),
            'recommended' => 0,
            'high_opportunity' => 0,
            'needs_scoring' => 0,
        ];

        foreach ($activeCompanies as $company) {
            $scores = $company->scores->whereIn('axis', $scoreAxes)->keyBy('axis');

            $scoredAxesCount = collect($scoreAxes)
                ->filter(fn (string $axis) => $scores->get($axis)?->value !== null)
                ->count();

            $autoSuggestionCount = $scores
                ->filter(fn ($score) => $score->auto_suggested_value !== null)
                ->count();

            $manualAdjustedCount = $scores
                ->filter(fn ($score) => $score->auto_suggested_value !== null && (int) $score->value !== (int) $score->auto_suggested_value)
                ->count();

            if ($scoredAxesCount === 0) {
                $scoreSummary['unscored']++;
            } elseif ($scoredAxesCount < count($scoreAxes)) {
                $scoreSummary['partial']++;
            } else {
                $scoreSummary['fully_scored']++;
            }

            if ($autoSuggestionCount > 0) {
                $scoreSummary['has_auto_suggestion']++;
            }

            if ($manualAdjustedCount > 0) {
                $scoreSummary['manual_adjusted']++;
            }

            if ($autoSuggestionCount > 0 && $manualAdjustedCount === 0) {
                $scoreSummary['suggestion_as_is']++;
            }

            if ($scoredAxesCount < count($scoreAxes)) {
                $candidateSummary['needs_scoring']++;
                continue;
            }

            $hpWeakness = $scores->get('hp_weakness')?->value ?? 0;
            $selfUpdateFit = $scores->get('self_update_fit')?->value ?? 0;
            $devDifficulty = $scores->get('dev_difficulty')?->value ?? 0;
            $portalDependence = $scores->get('portal_dependence')?->value ?? 0;

            $opportunityScore = $hpWeakness + $selfUpdateFit;
            $riskScore = $devDifficulty + $portalDependence;

            if ($opportunityScore >= 7) {
                $candidateSummary['high_opportunity']++;
            }

            if ($opportunityScore >= 7 && $riskScore <= 3) {
                $candidateSummary['recommended']++;
            }
        }

        $summary = [
            'source_records' => [
                'total' => SourceRecord::query()->count(),
                'linked' => SourceRecord::query()->has('sourceLink')->count(),
                'unlinked' => SourceRecord::query()->doesntHave('sourceLink')->count(),
            ],
            'companies' => [
                'total' => $companies->count(),
                'active' => $activeCompanies->count(),
                'killed' => $companies->filter(fn (Company $company) => (bool) $company->is_killed)->count(),
                'merged' => $companies->filter(fn (Company $company) => $company->status === 'merged')->count(),
            ],
            'scores' => $scoreSummary,
            'candidates' => $candidateSummary,
        ];

        $nextSourceRecords = SourceRecord::query()
            ->doesntHave('sourceLink')
            ->orderBy('id')
            ->limit(5)
            ->get();

        $scoringQueue = $activeCompanies
            ->map(function (Company $company) use ($scoreAxes) {
                $scores = $company->scores->whereIn('axis', $scoreAxes)->keyBy('axis');
                $scoredAxesCount = collect($scoreAxes)
                    ->filter(fn (string $axis) => $scores->get($axis)?->value !== null)
                    ->count();

                $company->setAttribute('dashboard_scored_axes_count', $scoredAxesCount);

                return $company;
            })
            ->filter(fn (Company $company) => $company->dashboard_scored_axes_count < count($scoreAxes))
            ->sortBy('dashboard_scored_axes_count')
            ->take(5)
            ->values();

        $recommendedQueue = $activeCompanies
            ->map(function (Company $company) use ($scoreAxes) {
                $scores = $company->scores->whereIn('axis', $scoreAxes)->keyBy('axis');
                $scoredAxesCount = collect($scoreAxes)
                    ->filter(fn (string $axis) => $scores->get($axis)?->value !== null)
                    ->count();

                $hpWeakness = $scores->get('hp_weakness')?->value ?? 0;
                $selfUpdateFit = $scores->get('self_update_fit')?->value ?? 0;
                $devDifficulty = $scores->get('dev_difficulty')?->value ?? 0;
                $portalDependence = $scores->get('portal_dependence')?->value ?? 0;

                $opportunityScore = $hpWeakness + $selfUpdateFit;
                $riskScore = $devDifficulty + $portalDependence;

                $company->setAttribute('dashboard_scored_axes_count', $scoredAxesCount);
                $company->setAttribute('dashboard_opportunity_score', $opportunityScore);
                $company->setAttribute('dashboard_risk_score', $riskScore);
                $company->setAttribute('dashboard_priority_score', ($opportunityScore * 10) - ($riskScore * 6));

                return $company;
            })
            ->filter(fn (Company $company) =>
                $company->dashboard_scored_axes_count === count($scoreAxes)
                && $company->dashboard_opportunity_score >= 7
                && $company->dashboard_risk_score <= 3
            )
            ->sortByDesc('dashboard_priority_score')
            ->take(5)
            ->values();

        $workBoard = [
            'next_source_records' => $nextSourceRecords,
            'scoring_queue' => $scoringQueue,
            'recommended_queue' => $recommendedQueue,
        ];

        $activeIds = $activeCompanies->pluck('id');
        $v2RankCounts = CompanyScoreSummary::query()
            ->where('score_version', 'scoring_v1.0')
            ->whereIn('company_id', $activeIds)
            ->selectRaw('`rank`, COUNT(*) as cnt')
            ->groupByRaw('`rank`')
            ->pluck('cnt', 'rank');

        $v2Summary = [
            'rank_a'          => (int) ($v2RankCounts['A'] ?? 0),
            'rank_b'          => (int) ($v2RankCounts['B'] ?? 0),
            'rank_a_low_conf' => CompanyScoreSummary::query()
                ->where('score_version', 'scoring_v1.0')
                ->whereIn('company_id', $activeIds)
                ->where('rank', 'A')
                ->where('confidence', '<', 0.70)
                ->count(),
        ];

        return view('dashboard', compact('summary', 'workBoard', 'v2Summary'));
    }
}
