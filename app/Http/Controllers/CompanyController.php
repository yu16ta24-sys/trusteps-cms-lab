<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyKillFlag;
use App\Models\HpFact;
use App\Models\CompanyScore;
use App\Models\CompanySourceLink;
use App\Models\Domain;
use App\Models\Industry;
use App\Models\Municipality;
use App\Models\SourceRecord;
use App\Services\HpAnalyzerService;
use App\Services\ScoreSuggester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyController extends Controller
{
    private const DIRECTORY_SOURCE_TYPES = ['directory_source_candidate'];

    public function index(Request $request): View
    {
        $query = Company::query()
            ->with([
                'industry',
                'municipality.prefecture',
                'primaryDomain',
                'mergedInto',
                'scores' => fn ($scoreQuery) => $scoreQuery->where('algo_version', 'v1'),
            ])
            ->withCount(['sourceLinks', 'domains', 'killFlags'])
            ->latest('id');

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($q) {
                $inner
                    ->where('display_name', 'like', "%{$q}%")
                    ->orWhere('legal_name', 'like', "%{$q}%")
                    ->orWhere('name_norm', 'like', "%{$q}%")
                    ->orWhere('corporate_number', 'like', "%{$q}%")
                    ->orWhere('pref', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%");
            });
        }

        if ($request->filled('industry_id')) {
            $query->where('industry_id', $request->input('industry_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('kill_state')) {
            if ($request->input('kill_state') === 'active') {
                $query->where('is_killed', false);
            }

            if ($request->input('kill_state') === 'killed') {
                $query->where('is_killed', true);
            }
        }

        $scoreState = (string) $request->input('score_state', '');
        if ($scoreState === 'unscored') {
            $query->whereDoesntHave('scores', fn ($scoreQuery) =>
                $scoreQuery->where('algo_version', 'v1')
            );
        } elseif ($scoreState === 'partial') {
            $query
                ->whereHas('scores', fn ($scoreQuery) => $scoreQuery->where('algo_version', 'v1'))
                ->where(function ($missingAxisQuery) {
                    foreach (array_keys($this->scoreAxisOptions()) as $axis) {
                        $missingAxisQuery->orWhereDoesntHave('scores', fn ($scoreQuery) =>
                            $scoreQuery->where('algo_version', 'v1')->where('axis', $axis)
                        );
                    }
                });
        } elseif ($scoreState === 'fully_scored') {
            foreach (array_keys($this->scoreAxisOptions()) as $axis) {
                $query->whereHas('scores', fn ($scoreQuery) =>
                    $scoreQuery->where('algo_version', 'v1')->where('axis', $axis)
                );
            }
        } elseif ($scoreState === 'has_auto_suggestion') {
            $query->whereHas('scores', fn ($scoreQuery) =>
                $scoreQuery->where('algo_version', 'v1')->whereNotNull('auto_suggested_value')
            );
        } elseif ($scoreState === 'manual_adjusted') {
            $query->whereHas('scores', fn ($scoreQuery) =>
                $scoreQuery
                    ->where('algo_version', 'v1')
                    ->whereNotNull('auto_suggested_value')
                    ->whereColumn('value', '!=', 'auto_suggested_value')
            );
        } elseif ($scoreState === 'suggestion_as_is') {
            $query
                ->whereHas('scores', fn ($scoreQuery) =>
                    $scoreQuery->where('algo_version', 'v1')->whereNotNull('auto_suggested_value')
                )
                ->whereDoesntHave('scores', fn ($scoreQuery) =>
                    $scoreQuery
                        ->where('algo_version', 'v1')
                        ->whereNotNull('auto_suggested_value')
                        ->whereColumn('value', '!=', 'auto_suggested_value')
                );
        }

        $companies = $query->paginate(30)->withQueryString();

        $industries = Industry::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $totalCount = Company::query()->count();
        $activeCount = Company::query()->where('is_killed', false)->count();
        $killedCount = Company::query()->where('is_killed', true)->count();
        $scoredCount = Company::query()
            ->whereHas('scores', fn ($scoreQuery) => $scoreQuery->where('algo_version', 'v1'))
            ->count();

        return view('companies.index', compact(
            'companies',
            'industries',
            'totalCount',
            'activeCount',
            'killedCount',
            'scoredCount'
        ));
    }


    public function candidates(Request $request): View
    {
        $query = Company::query()
            ->with([
                'industry',
                'municipality.prefecture',
                'primaryDomain',
                'scores'                => fn ($scoreQuery) => $scoreQuery->where('algo_version', 'v1'),
                'latestOutreachContact',
            ])
            ->withCount(['sourceLinks', 'domains', 'killFlags'])
            ->where('is_killed', false)
            ->where('status', '!=', 'merged');

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($q) {
                $inner
                    ->where('display_name', 'like', "%{$q}%")
                    ->orWhere('legal_name', 'like', "%{$q}%")
                    ->orWhere('name_norm', 'like', "%{$q}%")
                    ->orWhere('corporate_number', 'like', "%{$q}%")
                    ->orWhere('pref', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhereHas('industry', fn ($industryQuery) => $industryQuery->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('municipality', fn ($municipalityQuery) => $municipalityQuery->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('municipality.prefecture', fn ($prefectureQuery) => $prefectureQuery->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('primaryDomain', fn ($domainQuery) => $domainQuery->where('normalized_domain', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('industry_id')) {
            $query->where('industry_id', $request->input('industry_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $all = $query->get();

        // primaryDomain 経由で最新HpFactをドメインIDキーで一括取得（N+1回避）
        $domainIds = $all->pluck('primaryDomain.id')->filter()->unique()->values()->all();
        $latestFactsByDomainId = collect();
        if (!empty($domainIds)) {
            $latestFactsByDomainId = HpFact::query()
                ->join('hp_snapshots', 'hp_facts.hp_snapshot_id', '=', 'hp_snapshots.id')
                ->whereIn('hp_snapshots.domain_id', $domainIds)
                ->whereNotNull('hp_facts.extracted_at')
                ->orderByDesc('hp_facts.extracted_at')
                ->select('hp_facts.*', 'hp_snapshots.domain_id')
                ->get()
                ->groupBy('domain_id')
                ->map(fn ($group) => $group->first());
        }

        $companies = $all
            ->map(function (Company $company) use ($latestFactsByDomainId) {
                $scores = $company->scores->keyBy('axis');

                $hpWeakness = optional($scores->get('hp_weakness'))->value;
                $selfUpdateFit = optional($scores->get('self_update_fit'))->value;
                $devDifficulty = optional($scores->get('dev_difficulty'))->value;
                $portalDependence = optional($scores->get('portal_dependence'))->value;

                $scoredAxesCount = collect([$hpWeakness, $selfUpdateFit, $devDifficulty, $portalDependence])
                    ->filter(fn ($value) => $value !== null)
                    ->count();

                $autoSuggestionCount = $scores
                    ->filter(fn ($score) => $score->auto_suggested_value !== null)
                    ->count();

                $manualAdjustedCount = $scores
                    ->filter(fn ($score) => $score->auto_suggested_value !== null && (int) $score->value !== (int) $score->auto_suggested_value)
                    ->count();

                $opportunityScore = ($hpWeakness ?? 0) + ($selfUpdateFit ?? 0);
                $riskScore = ($devDifficulty ?? 0) + ($portalDependence ?? 0);

                [$judgment, $judgmentClass] = $this->scoreJudgment($opportunityScore, $riskScore, $scoredAxesCount);

                $hpFact = $latestFactsByDomainId->get($company->primaryDomain?->id);
                $company->setRelation('latestHpFact', $hpFact);

                $contactPenalty = 0;
                if ($hpFact) {
                    if (!$hpFact->hp_contact_email && !$hpFact->hp_contact_form_url && !$hpFact->hp_contact_phone) {
                        $contactPenalty = -3;
                    } elseif (!$hpFact->hp_contact_email && !$hpFact->hp_contact_form_url) {
                        $contactPenalty = -1; // 電話のみ
                    }
                }

                $priorityScore = $scoredAxesCount < 4
                    ? -100 + $scoredAxesCount + $contactPenalty
                    : ($opportunityScore * 10) + ($riskScore * 10) + min($company->source_links_count, 5) + $contactPenalty;

                $company->setAttribute('opportunity_score', $opportunityScore);
                $company->setAttribute('risk_score', $riskScore);
                $company->setAttribute('scored_axes_count', $scoredAxesCount);
                $company->setAttribute('auto_suggestion_count', $autoSuggestionCount);
                $company->setAttribute('manual_adjusted_count', $manualAdjustedCount);
                $company->setAttribute('candidate_judgment', $judgment);
                $company->setAttribute('candidate_judgment_class', $judgmentClass);
                $company->setAttribute('candidate_priority_score', $priorityScore);

                return $company;
            });

        $preset = $request->input('preset', 'recommended');

        if ($preset === 'recommended') {
            $companies = $companies->filter(fn ($company) =>
                $company->scored_axes_count === 4
                && $company->opportunity_score >= 7
                && $company->risk_score >= 7
            );
        } elseif ($preset === 'high_opportunity') {
            $companies = $companies->filter(fn ($company) =>
                $company->scored_axes_count === 4
                && $company->opportunity_score >= 7
            );
        } elseif ($preset === 'needs_scoring') {
            $companies = $companies->filter(fn ($company) => $company->scored_axes_count < 4);
        } elseif ($preset === 'all_active') {
            // no additional filter
        }

        $scoreState = (string) $request->input('score_state', '');
        if ($scoreState === 'unscored') {
            $companies = $companies->filter(fn ($company) => $company->scored_axes_count === 0);
        } elseif ($scoreState === 'partial') {
            $companies = $companies->filter(fn ($company) => $company->scored_axes_count > 0 && $company->scored_axes_count < 4);
        } elseif ($scoreState === 'fully_scored') {
            $companies = $companies->filter(fn ($company) => $company->scored_axes_count === 4);
        } elseif ($scoreState === 'has_auto_suggestion') {
            $companies = $companies->filter(fn ($company) => $company->auto_suggestion_count > 0);
        } elseif ($scoreState === 'manual_adjusted') {
            $companies = $companies->filter(fn ($company) => $company->manual_adjusted_count > 0);
        } elseif ($scoreState === 'suggestion_as_is') {
            $companies = $companies->filter(fn ($company) => $company->auto_suggestion_count > 0 && $company->manual_adjusted_count === 0);
        }

        $selectedPref = trim((string) $request->input('pref', ''));
        $selectedCity = trim((string) $request->input('city', ''));

        $prefOptions = $companies
            ->map(fn (Company $company) => $this->companyPrefLabel($company))
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL)
            ->values();

        $cityOptionSource = $selectedPref !== ''
            ? $companies->filter(fn (Company $company) => $this->companyPrefLabel($company) === $selectedPref)
            : $companies;

        $cityOptions = $cityOptionSource
            ->map(fn (Company $company) => $this->companyCityLabel($company))
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL)
            ->values();

        if ($selectedPref !== '') {
            $companies = $companies->filter(fn (Company $company) => $this->companyPrefLabel($company) === $selectedPref);
        }

        if ($selectedCity !== '') {
            $companies = $companies->filter(fn (Company $company) => $this->companyCityLabel($company) === $selectedCity);
        }

        $sort = (string) $request->input('sort', 'priority');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = [
            'priority', 'id', 'display_name', 'industry', 'region',
            'opportunity_score', 'risk_score', 'scored_axes_count',
            'source_links_count', 'domains_count', 'kill_flags_count',
            'domain', 'auto_suggestion_count', 'manual_adjusted_count',
        ];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'priority';
        }

        $companies = $companies
            ->sort(function ($a, $b) use ($sort, $direction) {
                $valueFor = function (Company $company) use ($sort) {
                    return match ($sort) {
                        'id' => $company->id,
                        'display_name' => $company->display_name ?? '',
                        'industry' => $company->industry?->name ?? '',
                        'region' => (($company->municipality?->prefecture?->name ?? $company->pref ?? '') . '/' . ($company->municipality?->name ?? $company->city ?? '')),
                        'opportunity_score' => $company->opportunity_score,
                        'risk_score' => $company->risk_score,
                        'scored_axes_count' => $company->scored_axes_count,
                        'source_links_count' => $company->source_links_count,
                        'domains_count' => $company->domains_count,
                        'kill_flags_count' => $company->kill_flags_count,
                        'domain' => $company->primaryDomain?->normalized_domain ?? '',
                        'auto_suggestion_count' => $company->auto_suggestion_count,
                        'manual_adjusted_count' => $company->manual_adjusted_count,
                        default => $company->candidate_priority_score,
                    };
                };

                $aValue = $valueFor($a);
                $bValue = $valueFor($b);

                if (is_numeric($aValue) && is_numeric($bValue)) {
                    $comparison = $aValue <=> $bValue;
                } else {
                    $comparison = strnatcasecmp((string) $aValue, (string) $bValue);
                }

                if ($comparison === 0) {
                    $comparison = $a->id <=> $b->id;
                }

                return $direction === 'asc' ? $comparison : -$comparison;
            })
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 30;
        $pagedCompanies = new LengthAwarePaginator(
            $companies->forPage($page, $perPage)->values(),
            $companies->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $industries = Industry::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $summaryBase = Company::query()
            ->where('is_killed', false)
            ->where('status', '!=', 'merged');

        $activeCandidateTotal = (clone $summaryBase)->count();

        return view('companies.candidates', [
            'companies' => $pagedCompanies,
            'industries' => $industries,
            'prefOptions' => $prefOptions,
            'cityOptions' => $cityOptions,
            'activeCandidateTotal' => $activeCandidateTotal,
            'filteredCount' => $companies->count(),
            'preset' => $preset,
            'sort' => $sort,
            'direction' => $direction,
            'scoreState' => $scoreState,
        ]);
    }

    public function show(Company $company): View
    {
        $company->load([
            'industry',
            'municipality.prefecture',
            'primaryDomain',
            'domains',
            'sourceLinks.sourceRecord',
            'mergedInto',
            'mergedChildren',
            'killFlags',
            'scores',
            'outreachContacts',
        ]);

        $scoreAxes = $this->scoreAxisOptions();
        $scoresByAxis = $company->scores
            ->where('algo_version', 'v1')
            ->keyBy('axis');

        try {
            $scoreSuggestions = app(\App\Services\ScoreSuggester::class)->suggest($company);
        } catch (\Throwable $e) {
            report($e);
            $scoreSuggestions = [];
        }

        $scoringQueueCount = $this->scoringQueueQuery()->count();
        $isCurrentScoringQueueTarget = $this->scoringQueueQuery()
            ->whereKey($company->id)
            ->exists();
        $previousScoringCompany = $this->scoringQueueQuery()
            ->where('id', '<', $company->id)
            ->orderByDesc('id')
            ->first(['id', 'display_name']);
        $nextScoringCompany = $this->scoringQueueQuery()
            ->where('id', '>', $company->id)
            ->orderBy('id')
            ->first(['id', 'display_name']);

        if (!$nextScoringCompany) {
            $nextScoringCompany = $this->scoringQueueQuery()
                ->where('id', '!=', $company->id)
                ->orderBy('id')
                ->first(['id', 'display_name']);
        }

        return view('companies.show', compact(
            'company',
            'scoreAxes',
            'scoresByAxis',
            'scoreSuggestions',
            'scoringQueueCount',
            'isCurrentScoringQueueTarget',
            'previousScoringCompany',
            'nextScoringCompany'
        ));
    }

    public function edit(Company $company): View|RedirectResponse
    {
        if ($company->status === 'merged') {
            return redirect()
                ->route('companies.show', $company)
                ->with('status', '統合済みcompanyは編集できない。');
        }

        $industries = Industry::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $municipalities = Municipality::query()
            ->with('prefecture')
            ->orderBy('code')
            ->get();

        return view('companies.edit', compact('company', 'industries', 'municipalities'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        if ($company->status === 'merged') {
            return redirect()
                ->route('companies.show', $company)
                ->with('status', '統合済みcompanyは編集できない。');
        }

        $validated = $request->validate([
            'display_name'     => ['required', 'string', 'max:255'],
            'legal_name'       => ['nullable', 'string', 'max:255'],
            'corporate_number' => ['nullable', 'string', 'max:13'],
            'status'           => ['required', 'in:candidate,confirmed'],
            'industry_id'      => ['nullable', 'exists:industries,id'],
            'municipality_id'  => ['nullable', 'exists:municipalities,id'],
            'pref'             => ['nullable', 'string', 'max:50'],
            'city'             => ['nullable', 'string', 'max:100'],
            'primary_url'      => ['nullable', 'string', 'max:2000'],
        ]);

        $company->update([
            'display_name'     => $validated['display_name'],
            'legal_name'       => $validated['legal_name'] ?? null,
            'name_norm'        => $this->normalizeName($validated['display_name']),
            'corporate_number' => $this->normalizeCorporateNumber($validated['corporate_number'] ?? null),
            'status'           => $validated['status'],
            'industry_id'      => $validated['industry_id'] ?? null,
            'municipality_id'  => $validated['municipality_id'] ?? null,
            'pref'             => empty($validated['municipality_id']) ? ($validated['pref'] ?? null) : null,
            'city'             => empty($validated['municipality_id']) ? ($validated['city'] ?? null) : null,
        ]);

        $newUrl = trim((string) ($validated['primary_url'] ?? ''));
        $currentUrl = $company->primaryDomain?->url ?? '';

        if ($newUrl !== '' && $newUrl !== $currentUrl) {
            $domain = Domain::create([
                'company_id'        => $company->id,
                'url'               => $newUrl,
                'normalized_domain' => $this->normalizeDomain($newUrl),
                'role'              => 'official',
                'is_primary'        => true,
                'is_portal'         => false,
            ]);

            if ($company->primary_domain_id) {
                Domain::where('id', $company->primary_domain_id)->update(['is_primary' => false]);
            }

            $company->update(['primary_domain_id' => $domain->id]);
        }

        return redirect()
            ->route('companies.show', $company)
            ->with('status', 'company情報を更新した。');
    }

    public function analyze(Request $request, Company $company): RedirectResponse
    {
        if (!$company->primaryDomain) {
            return redirect()->route('companies.show', $company)->with('status', 'primary_domainが未設定のため解析できません。');
        }
        try {
            $analyzer = app(HpAnalyzerService::class);
            $result   = $analyzer->analyze($company);
            if (!$result['success']) {
                return redirect()->route('companies.show', $company)->with('status', 'HP解析失敗：' . $result['message']);
            }
            if ($result['js_rendering_required'] ?? false) {
                return redirect()->route('companies.show', $company)->with('status', 'JSサイトのため自動解析できませんでした。目視で確認してください。');
            }
            $company->load(['industry', 'domains', 'primaryDomain', 'scores']);
            $suggestions = app(\App\Services\ScoreSuggester::class)->suggest($company);
            $axisKeys    = array_keys($this->scoreAxisOptions());
            $savedCount  = 0;
            foreach ($axisKeys as $axis) {
                $suggestion = $suggestions[$axis] ?? null;
                if (!$suggestion || $suggestion['value'] === null) {
                    continue;
                }
                $value      = (int) $suggestion['value'];
                $confidence = in_array($suggestion['confidence'], ['0.3', '0.6', '0.9']) ? $suggestion['confidence'] : '0.3';
                $reasonJson = [
                    'basis'   => 'hp_analysis_auto',
                    'drivers' => $suggestion['drivers'] ?? [],
                    'note'    => $suggestion['note'] ?? null,
                    'auto_suggestion' => [
                        'algo_version' => \App\Services\ScoreSuggester::ALGO,
                        'value'        => $value,
                        'confidence'   => $confidence,
                        'basis'        => $suggestion['basis'] ?? 'auto',
                        'drivers'      => $suggestion['drivers'] ?? [],
                        'note'         => $suggestion['note'] ?? null,
                    ],
                ];
                CompanyScore::updateOrCreate(
                    ['company_id' => $company->id, 'axis' => $axis, 'algo_version' => 'v1'],
                    ['value' => $value, 'confidence' => $confidence, 'auto_suggested_value' => $value, 'reason_json' => $reasonJson, 'scored_by' => 'hp_analysis_auto', 'scored_at' => now()]
                );
                $savedCount++;
            }
            return redirect()->route('companies.show', $company)->with('status', "HP解析完了。{$savedCount}軸のスコアを自動保存しました。");
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('companies.show', $company)->with('status', 'HP解析中にエラーが発生しました。');
        }
    }

    public function setPrimaryUrl(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'primary_url' => ['required', 'string', 'url', 'max:500'],
        ]);

        $newUrl = trim($validated['primary_url']);
        $currentUrl = $company->primaryDomain?->url ?? '';

        if ($newUrl !== $currentUrl) {
            $domain = Domain::create([
                'company_id'        => $company->id,
                'url'               => $newUrl,
                'normalized_domain' => $this->normalizeDomain($newUrl),
                'role'              => 'official',
                'is_primary'        => true,
                'is_portal'         => false,
            ]);

            if ($company->primary_domain_id) {
                Domain::where('id', $company->primary_domain_id)->update(['is_primary' => false]);
            }

            $company->update(['primary_domain_id' => $domain->id]);
        }

        return redirect()->route('companies.show', $company)->with('status', '公式URLを更新しました。再度「HP解析」を実行してください。');
    }

    public function createFromSource(SourceRecord $sourceRecord): View|RedirectResponse
    {
        $sourceRecord->load('sourceLink.company');

        if ($sourceRecord->sourceLink) {
            return redirect()
                ->route('companies.show', $sourceRecord->sourceLink->company)
                ->with('status', 'このsource_recordはすでにcompanyへリンク済み。');
        }

        if ($this->isDirectorySourceRecord($sourceRecord)) {
            return redirect()
                ->route('source-records.show', $sourceRecord)
                ->with('status', 'このsource_recordは名簿元のため、営業先companyには変換しない。');
        }

        $industries = Industry::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $municipalities = Municipality::query()
            ->with('prefecture')
            ->orderBy('code')
            ->get();

        $defaults = $this->defaultsFromSourceRecord($sourceRecord);

        return view('companies.create_from_source', compact(
            'sourceRecord',
            'industries',
            'municipalities',
            'defaults',
        ));
    }

    public function storeFromSource(Request $request, SourceRecord $sourceRecord): RedirectResponse
    {
        $sourceRecord->load('sourceLink.company');

        if ($sourceRecord->sourceLink) {
            return redirect()
                ->route('companies.show', $sourceRecord->sourceLink->company)
                ->with('status', 'このsource_recordはすでにcompanyへリンク済み。');
        }

        if ($this->isDirectorySourceRecord($sourceRecord)) {
            return redirect()
                ->route('source-records.show', $sourceRecord)
                ->with('status', 'このsource_recordは名簿元のため、営業先companyには変換しない。');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:candidate,confirmed'],
            'display_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'industry_id' => ['nullable', 'exists:industries,id'],
            'municipality_id' => ['nullable', 'exists:municipalities,id'],
            'corporate_number' => ['nullable', 'string', 'max:13'],
            'pref' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'primary_url' => ['nullable', 'string', 'max:2000'],
            'match_type' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:5000'],
            'after_action' => ['nullable', 'in:company,next_source'],
        ]);

        $company = DB::transaction(function () use ($validated, $sourceRecord) {
            $company = Company::create([
                'status' => $validated['status'],
                'municipality_id' => $validated['municipality_id'] ?? null,
                'industry_id' => $validated['industry_id'] ?? null,
                'primary_domain_id' => null,
                'legal_name' => $validated['legal_name'] ?? null,
                'display_name' => $validated['display_name'],
                'name_norm' => $this->normalizeName($validated['display_name']),
                'alias_names_json' => null,
                'corporate_number' => $this->normalizeCorporateNumber($validated['corporate_number'] ?? null),
                'pref' => !empty($validated['municipality_id']) ? null : ($validated['pref'] ?? null),
                'city' => !empty($validated['municipality_id']) ? null : ($validated['city'] ?? null),
                'is_killed' => false,
            ]);

            if (!empty($validated['primary_url'])) {
                $domain = Domain::create([
                    'company_id' => $company->id,
                    'url' => $validated['primary_url'],
                    'normalized_domain' => $this->normalizeDomain($validated['primary_url']),
                    'role' => 'official',
                    'is_primary' => true,
                    'is_portal' => false,
                ]);

                $company->update([
                    'primary_domain_id' => $domain->id,
                ]);
            }

            CompanySourceLink::create([
                'company_id' => $company->id,
                'source_record_id' => $sourceRecord->id,
                'match_type' => $validated['match_type'],
                'match_confidence' => 1.00,
                'created_by' => auth()->user()?->email ?? 'manual',
            ]);

            return $company;
        });

        if (($validated['after_action'] ?? null) === 'next_source') {
            return $this->redirectToNextUnlinkedSourceRecord(
                $sourceRecord,
                "source_recordからcompany #{$company->id} を作成した。次の未リンクへ進む。",
                "source_recordからcompany #{$company->id} を作成した。未リンクsource_recordは残っていない。"
            );
        }

        return redirect()
            ->route('companies.show', $company)
            ->with('status', 'source_recordからcompanyを作成した。');
    }

    public function linkExistingFromSource(Request $request, SourceRecord $sourceRecord): View|RedirectResponse
    {
        $sourceRecord->load('sourceLink.company');

        if ($sourceRecord->sourceLink) {
            return redirect()
                ->route('companies.show', $sourceRecord->sourceLink->company)
                ->with('status', 'このsource_recordはすでにcompanyへリンク済み。');
        }

        if ($this->isDirectorySourceRecord($sourceRecord)) {
            return redirect()
                ->route('source-records.show', $sourceRecord)
                ->with('status', 'このsource_recordは名簿元のため、既存companyへリンクしない。');
        }

        $query = Company::query()
            ->with(['industry', 'municipality.prefecture', 'primaryDomain'])
            ->where('status', '!=', 'merged')
            ->latest('id');

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($q) {
                $inner
                    ->where('display_name', 'like', "%{$q}%")
                    ->orWhere('legal_name', 'like', "%{$q}%")
                    ->orWhere('name_norm', 'like', "%{$q}%")
                    ->orWhere('corporate_number', 'like', "%{$q}%")
                    ->orWhere('pref', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%");
            });
        }

        $companies = $query->paginate(20)->withQueryString();

        return view('companies.link_existing_from_source', compact('sourceRecord', 'companies'));
    }

    public function storeLinkExistingFromSource(Request $request, SourceRecord $sourceRecord): RedirectResponse
    {
        $sourceRecord->load('sourceLink.company');

        if ($sourceRecord->sourceLink) {
            return redirect()
                ->route('companies.show', $sourceRecord->sourceLink->company)
                ->with('status', 'このsource_recordはすでにcompanyへリンク済み。');
        }

        if ($this->isDirectorySourceRecord($sourceRecord)) {
            return redirect()
                ->route('source-records.show', $sourceRecord)
                ->with('status', 'このsource_recordは名簿元のため、既存companyへリンクしない。');
        }

        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'match_type' => ['required', 'in:manual_same'],
            'after_action' => ['nullable', 'in:company,next_source'],
        ]);

        $company = Company::query()
            ->where('status', '!=', 'merged')
            ->findOrFail($validated['company_id']);

        CompanySourceLink::create([
            'company_id' => $company->id,
            'source_record_id' => $sourceRecord->id,
            'match_type' => $validated['match_type'],
            'match_confidence' => 1.00,
            'created_by' => auth()->user()?->email ?? 'manual',
        ]);

        if (($validated['after_action'] ?? null) === 'next_source') {
            return $this->redirectToNextUnlinkedSourceRecord(
                $sourceRecord,
                "source_recordを既存company #{$company->id} へリンクした。次の未リンクへ進む。",
                "source_recordを既存company #{$company->id} へリンクした。未リンクsource_recordは残っていない。"
            );
        }

        return redirect()
            ->route('companies.show', $company)
            ->with('status', 'source_recordを既存companyへリンクした。');
    }

    private function redirectToNextUnlinkedSourceRecord(SourceRecord $currentSourceRecord, string $foundMessage, string $emptyMessage): RedirectResponse
    {
        $nextSourceRecord = SourceRecord::query()
            ->whereDoesntHave('sourceLink')
            ->where('id', '>', $currentSourceRecord->id)
            ->orderBy('id')
            ->first();

        if (!$nextSourceRecord) {
            $nextSourceRecord = SourceRecord::query()
                ->whereDoesntHave('sourceLink')
                ->orderBy('id')
                ->first();
        }

        if ($nextSourceRecord) {
            return redirect()
                ->route('source-records.show', $nextSourceRecord)
                ->with('status', $foundMessage);
        }

        return redirect()
            ->route('source-records.index', ['link_status' => 'unlinked'])
            ->with('status', $emptyMessage);
    }

    public function mergeForm(Request $request, Company $company): View|RedirectResponse
    {
        if ($company->status === 'merged') {
            return redirect()
                ->route('companies.show', $company)
                ->with('status', 'このcompanyはすでに統合済み。先にUndoしてから操作する。');
        }

        $query = Company::query()
            ->with(['industry', 'municipality.prefecture', 'primaryDomain'])
            ->where('id', '!=', $company->id)
            ->where('status', '!=', 'merged')
            ->latest('id');

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($q) {
                $inner
                    ->where('display_name', 'like', "%{$q}%")
                    ->orWhere('legal_name', 'like', "%{$q}%")
                    ->orWhere('name_norm', 'like', "%{$q}%")
                    ->orWhere('corporate_number', 'like', "%{$q}%")
                    ->orWhere('pref', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%");
            });
        }

        $targetCompanies = $query->paginate(20)->withQueryString();

        return view('companies.merge', compact('company', 'targetCompanies'));
    }

    public function merge(Request $request, Company $company): RedirectResponse
    {
        if ($company->status === 'merged') {
            return redirect()
                ->route('companies.show', $company)
                ->with('status', 'このcompanyはすでに統合済み。');
        }

        $validated = $request->validate([
            'target_company_id' => ['required', 'exists:companies,id'],
            'merge_reason' => ['required', 'string', 'max:5000'],
        ]);

        if ((int) $validated['target_company_id'] === (int) $company->id) {
            return back()->withErrors(['target_company_id' => '自分自身には統合できない。']);
        }

        $targetCompany = Company::query()
            ->where('status', '!=', 'merged')
            ->findOrFail($validated['target_company_id']);

        $company->update([
            'merge_previous_status' => $company->status,
            'status' => 'merged',
            'merged_into_id' => $targetCompany->id,
            'merged_at' => now(),
            'merged_by' => auth()->user()?->email ?? 'manual',
            'merge_reason' => $validated['merge_reason'],
        ]);

        return redirect()
            ->route('companies.show', $company)
            ->with('status', "company #{$company->id} を company #{$targetCompany->id} へ統合した。source linksは書き換えていない。");
    }

    public function undoMerge(Company $company): RedirectResponse
    {
        if ($company->status !== 'merged') {
            return redirect()
                ->route('companies.show', $company)
                ->with('status', 'このcompanyはmergedではないためUndo不要。');
        }

        $previousStatus = $company->merge_previous_status ?: 'candidate';

        if (!in_array($previousStatus, ['candidate', 'confirmed'], true)) {
            $previousStatus = 'candidate';
        }

        $company->update([
            'status' => $previousStatus,
            'merged_into_id' => null,
            'merge_previous_status' => null,
            'merged_at' => null,
            'merged_by' => null,
            'merge_reason' => null,
        ]);

        return redirect()
            ->route('companies.show', $company)
            ->with('status', "company #{$company->id} の統合をUndoした。");
    }

    public function storeScores(Request $request, Company $company): RedirectResponse
    {
        $axisKeys = array_keys($this->scoreAxisOptions());

        $validated = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*.value' => ['required', 'integer', 'min:0', 'max:5'],
            'scores.*.confidence' => ['required', 'in:0.3,0.6,0.9'],
            'scores.*.note' => ['nullable', 'string', 'max:5000'],
            'after_action' => ['nullable', 'in:company,next_scoring'],
        ]);

        $scoreSuggestions = $request->input('score_suggestions', []);
        if (!is_array($scoreSuggestions)) {
            $scoreSuggestions = [];
        }

        foreach ($validated['scores'] as $axis => $scoreInput) {
            if (!in_array($axis, $axisKeys, true)) {
                continue;
            }

            $note = trim((string) ($scoreInput['note'] ?? ''));
            $autoSuggestion = $this->normalizeScoreSuggestion($scoreSuggestions[$axis] ?? null);

            $reasonJson = [
                'basis' => $autoSuggestion['value'] !== null ? 'manual_with_auto_suggestion' : 'manual',
                'drivers' => $autoSuggestion['drivers'],
                'evidence' => [],
                'note' => $note !== '' ? $note : null,
                'auto_suggestion' => $autoSuggestion['value'] !== null ? [
                    'algo_version' => $autoSuggestion['algo_version'],
                    'value' => $autoSuggestion['value'],
                    'confidence' => $autoSuggestion['confidence'],
                    'basis' => $autoSuggestion['basis'],
                    'drivers' => $autoSuggestion['drivers'],
                    'note' => $autoSuggestion['note'],
                ] : null,
            ];

            CompanyScore::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'axis' => $axis,
                    'algo_version' => 'v1',
                ],
                [
                    'value' => (int) $scoreInput['value'],
                    'confidence' => (string) $scoreInput['confidence'],
                    'auto_suggested_value' => $autoSuggestion['value'],
                    'reason_json' => $reasonJson,
                    'scored_by' => auth()->user()?->email ?? 'manual',
                    'scored_at' => now(),
                ]
            );
        }

        if (($validated['after_action'] ?? null) === 'next_scoring') {
            return $this->redirectToNextScoringCompany(
                $company,
                '4軸スコアを保存した。次の未採点companyへ進む。',
                '4軸スコアを保存した。未採点companyは残っていない。'
            );
        }

        return redirect()
            ->route('companies.show', $company)
            ->with('status', '4軸スコアを保存した。自動提案がある軸は提案値も記録した。');
    }

    private function scoringQueueQuery()
    {
        $query = Company::query()
            ->where('is_killed', false)
            ->where('status', '!=', 'merged');

        $query->where(function ($missingAxisQuery) {
            foreach (array_keys($this->scoreAxisOptions()) as $axis) {
                $missingAxisQuery->orWhereDoesntHave('scores', fn ($scoreQuery) =>
                    $scoreQuery
                        ->where('algo_version', 'v1')
                        ->where('axis', $axis)
                );
            }
        });

        return $query;
    }

    private function redirectToNextScoringCompany(Company $currentCompany, string $foundMessage, string $emptyMessage): RedirectResponse
    {
        $nextCompany = $this->scoringQueueQuery()
            ->where('id', '>', $currentCompany->id)
            ->orderBy('id')
            ->first(['id', 'display_name']);

        if (!$nextCompany) {
            $nextCompany = $this->scoringQueueQuery()
                ->where('id', '!=', $currentCompany->id)
                ->orderBy('id')
                ->first(['id', 'display_name']);
        }

        if ($nextCompany) {
            return redirect()
                ->route('companies.show', $nextCompany)
                ->with('status', $foundMessage);
        }

        return redirect()
            ->route('companies.index', ['score_state' => 'fully_scored'])
            ->with('status', $emptyMessage);
    }

    private function normalizeScoreSuggestion($input): array
    {
        $default = [
            'value' => null,
            'confidence' => null,
            'basis' => 'auto',
            'drivers' => [],
            'note' => null,
            'algo_version' => ScoreSuggester::ALGO,
        ];

        if (!is_array($input)) {
            return $default;
        }

        $value = $input['value'] ?? null;
        if (!is_numeric($value)) {
            return $default;
        }

        $value = (int) $value;
        if ($value < 0 || $value > 5) {
            return $default;
        }

        $confidence = (string) ($input['confidence'] ?? '');
        if (!in_array($confidence, ['0.3', '0.6', '0.9'], true)) {
            $confidence = null;
        }

        $basis = trim((string) ($input['basis'] ?? 'auto'));
        if ($basis === '') {
            $basis = 'auto';
        }

        $note = trim((string) ($input['note'] ?? ''));
        $algoVersion = trim((string) ($input['algo_version'] ?? ScoreSuggester::ALGO));
        if ($algoVersion === '') {
            $algoVersion = ScoreSuggester::ALGO;
        }

        $drivers = [];
        $driversRaw = $input['drivers_json'] ?? [];
        if (is_string($driversRaw) && $driversRaw !== '') {
            $decoded = json_decode($driversRaw, true);
            if (is_array($decoded)) {
                $driversRaw = $decoded;
            }
        }

        if (is_array($driversRaw)) {
            foreach ($driversRaw as $driver) {
                if (!is_scalar($driver)) {
                    continue;
                }

                $driver = trim((string) $driver);
                if ($driver !== '') {
                    $drivers[] = mb_substr($driver, 0, 120);
                }
            }
        }

        return [
            'value' => $value,
            'confidence' => $confidence,
            'basis' => mb_substr($basis, 0, 80),
            'drivers' => array_values(array_unique($drivers)),
            'note' => $note !== '' ? mb_substr($note, 0, 500) : null,
            'algo_version' => mb_substr($algoVersion, 0, 80),
        ];
    }

    private function scoreJudgment(int $opportunityScore, int $riskScore, int $scoredAxesCount): array
    {
        if ($scoredAxesCount < 4) {
            return ['未採点あり', 'gray'];
        }

        $totalScore = $opportunityScore + $riskScore;

        if ($totalScore >= 16) {
            return ['高ポテンシャル', 'green'];
        }

        if ($totalScore >= 12) {
            return ['ポテンシャルあり', 'blue'];
        }

        if ($totalScore >= 8) {
            return ['要確認', 'amber'];
        }

        return ['優先度低', 'gray'];
    }

    private function companyPrefLabel(Company $company): ?string
    {
        return $company->municipality?->prefecture?->name ?: $company->pref;
    }

    private function companyCityLabel(Company $company): ?string
    {
        return $company->municipality?->name ?: $company->city;
    }

    private function scoreAxisOptions(): array
    {
        return [
            'hp_weakness' => [
                'label' => 'HP弱点度',
                'group' => '機会',
                'polarity' => '高いほどチャンス',
                'description' => '現状HPがどれだけ機能不全・古さ・更新停止などの問題を抱えているか。',
                'anchor_0' => '弱点なし。更新され、スマホ対応・SSL・導線も問題ない。',
                'anchor_3' => '更新停止や古さなど、部分的な弱点がある。',
                'anchor_5' => '長期更新停止、非スマホ対応、SSLなし、表示崩れなど重大欠陥が複数ある。',
            ],
            'self_update_fit' => [
                'label' => '自走更新化適性',
                'group' => '機会',
                'polarity' => '高いほどチャンス',
                'description' => 'お知らせ・施工事例・活動報告など、型化できる更新枠と継続ネタがあるか。',
                'anchor_0' => '1枚もの・会社情報のみで、更新枠や継続ネタがほぼない。',
                'anchor_3' => 'お知らせ等の枠はあるが、頻度や更新ネタは限定的。',
                'anchor_5' => '事例・入荷・活動報告など、継続更新すべき明確な枠が複数ある。',
            ],
            'dev_difficulty' => [
                'label' => '開発・運用しやすさ',
                'group' => '機会',
                'polarity' => '高いほどチャンス',
                'description' => '予約・決済・在庫・物件DB・規制対応などを必要とせず、MVPスコープで完結しやすいか。',
                'anchor_0' => '予約エンジン、決済、在庫/物件DB、強い広告規制などが中核。',
                'anchor_3' => '軽い予約・問い合わせ・会員要素などが一部ある。',
                'anchor_5' => '静的情報提供と簡単な更新で完結する。',
            ],
            'portal_dependence' => [
                'label' => '自社HP自立度',
                'group' => '機会',
                'polarity' => '高いほどチャンス',
                'description' => '自社HPが実質的な集客・発信の窓口になっており、ポータル・SNS依存が低いか。',
                'anchor_0' => '自社HPが形骸化し、実質ポータルやSNSが窓口になっている。',
                'anchor_3' => '自社HPもあるが、SNSやポータルとの併用色が強い。',
                'anchor_5' => '自社HPが主な発信・集客窓口になっている。',
            ],
        ];
    }

    public function storeKillFlag(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'flag' => ['required', 'string', 'in:' . implode(',', array_keys($this->killFlagOptions()))],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        CompanyKillFlag::updateOrCreate(
            [
                'company_id' => $company->id,
                'flag' => $validated['flag'],
            ],
            [
                'note' => $validated['note'] ?? null,
                'source' => 'manual',
                'flagged_by' => auth()->user()?->email ?? 'manual',
                'flagged_at' => now(),
            ]
        );

        $company->update([
            'is_killed' => true,
        ]);

        return redirect()
            ->route('companies.show', $company)
            ->with('status', 'kill_flagを付与した。');
    }

    public function deleteKillFlag(Company $company, CompanyKillFlag $killFlag): RedirectResponse
    {
        if ((int) $killFlag->company_id !== (int) $company->id) {
            abort(404);
        }

        $killFlag->delete();

        $company->update([
            'is_killed' => $company->killFlags()->exists(),
        ]);

        return redirect()
            ->route('companies.show', $company)
            ->with('status', 'kill_flagを解除した。');
    }

    private function killFlagOptions(): array
    {
        return [
            'no_official_site' => '公式HPなし',
            'defunct' => '活動停止・閉業',
            'chain_no_edit_rights' => 'ローカル編集権限なし',
            'out_of_scope_size' => '対象外規模',
            'compliance_risk' => 'コンプライアンス・対象外属性',
        ];
    }

    private function defaultsFromSourceRecord(SourceRecord $sourceRecord): array
    {
        $raw = $sourceRecord->raw_json ?? [];
        $canonical = $raw['canonical'] ?? [];

        $companyName =
            $canonical['company_name']
            ?? $raw['company_name']
            ?? $sourceRecord->name_norm
            ?? '';

        return [
            'status' => 'candidate',
            'display_name' => $companyName,
            'legal_name' => null,
            'industry_id' => null,
            'municipality_id' => $this->guessMunicipalityId($sourceRecord->pref, $sourceRecord->city),
            'corporate_number' => $sourceRecord->corporate_number,
            'pref' => $sourceRecord->pref,
            'city' => $sourceRecord->city,
            'primary_url' => $sourceRecord->source_url,
            'match_type' => 'manual_new',
        ];
    }

    private function guessMunicipalityId(?string $pref, ?string $city): ?int
    {
        if (!$city) {
            return null;
        }

        $query = Municipality::query()->where('name', $city);

        if ($pref) {
            $query->whereHas('prefecture', function ($inner) use ($pref) {
                $inner->where('name', $pref);
            });
        }

        return $query->value('id');
    }

    private function normalizeCorporateNumber(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? $digits : null;
    }

    private function normalizeDomain(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $candidate = $url;
        if (!preg_match('#^https?://#i', $candidate)) {
            $candidate = 'https://' . $candidate;
        }

        $host = parse_url($candidate, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host);

        return $host ?: null;
    }

    private function normalizeName(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        $name = mb_convert_kana($name, 'asKV', 'UTF-8');
        $name = mb_strtolower($name);
        $name = preg_replace('/[\s　]+/u', '', $name);
        $name = str_replace(['株式会社', '有限会社', '合同会社', '（株）', '(株)', '㈱', '（有）', '(有)'], '', $name);

        return $name !== '' ? $name : null;
    }

    private function isDirectorySourceRecord(SourceRecord $sourceRecord): bool
    {
        return in_array($sourceRecord->source_type, self::DIRECTORY_SOURCE_TYPES, true);
    }
}
