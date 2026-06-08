<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyKillFlag;
use App\Models\CompanyScore;
use App\Models\CompanySourceLink;
use App\Models\Domain;
use App\Models\HpFact;
use App\Models\HpSnapshot;
use App\Models\Industry;
use App\Models\Municipality;
use App\Models\SnapshotUpdateTarget;
use App\Models\SourceRecord;
use App\Models\UpdateTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Illuminate\View\View;

class CompanyController extends Controller
{
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
                'scores' => fn ($scoreQuery) => $scoreQuery->where('algo_version', 'v1'),
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

        $companies = $query->get()
            ->map(function (Company $company) {
                $scores = $company->scores->keyBy('axis');

                $hpWeakness = optional($scores->get('hp_weakness'))->value;
                $selfUpdateFit = optional($scores->get('self_update_fit'))->value;
                $devDifficulty = optional($scores->get('dev_difficulty'))->value;
                $portalDependence = optional($scores->get('portal_dependence'))->value;

                $scoredAxesCount = collect([$hpWeakness, $selfUpdateFit, $devDifficulty, $portalDependence])
                    ->filter(fn ($value) => $value !== null)
                    ->count();

                $opportunityScore = ($hpWeakness ?? 0) + ($selfUpdateFit ?? 0);
                $riskScore = ($devDifficulty ?? 0) + ($portalDependence ?? 0);

                [$judgment, $judgmentClass] = $this->scoreJudgment($opportunityScore, $riskScore, $scoredAxesCount);

                $priorityScore = $scoredAxesCount < 4
                    ? -100 + $scoredAxesCount
                    : ($opportunityScore * 10) - ($riskScore * 6) + min($company->source_links_count, 5);

                $company->setAttribute('opportunity_score', $opportunityScore);
                $company->setAttribute('risk_score', $riskScore);
                $company->setAttribute('scored_axes_count', $scoredAxesCount);
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
                && $company->risk_score <= 3
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

        $selectedPref = trim((string) $request->input('pref', ''));
        $selectedCity = trim((string) $request->input('city', ''));

        // 地域プルダウンは全国マスタではなく、現在の候補母集団に実在する値だけを出す。
        // ここではキーワード/業種/状態/プリセット適用後、都道府県・市区町村フィルター適用前の候補から生成する。
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
            'priority',
            'id',
            'display_name',
            'industry',
            'region',
            'opportunity_score',
            'risk_score',
            'scored_axes_count',
            'source_links_count',
            'domains_count',
            'kill_flags_count',
            'domain',
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
        ]);

        $scoreAxes = $this->scoreAxisOptions();
        $scoresByAxis = $company->scores
            ->where('algo_version', 'v1')
            ->keyBy('axis');

        $latestHpSnapshot = null;
        $updateTargets = collect();
        $hpObservationOptions = $this->hpObservationOptions();
        $hpObservationAvailable = false;
        $hpObservationUnavailableReason = null;
        $hpObservationHasNoteColumn = false;
        $hpObservationHasPortalDependencyColumn = false;

        try {
            $requiredTables = ['domains', 'hp_snapshots', 'hp_facts', 'snapshot_update_targets', 'update_targets'];
            foreach ($requiredTables as $table) {
                if (!Schema::hasTable($table)) {
                    throw new \RuntimeException("required table missing: {$table}");
                }
            }

            $hpObservationHasNoteColumn = Schema::hasColumn('hp_snapshots', 'observation_note');
            $hpObservationHasPortalDependencyColumn = Schema::hasColumn('hp_facts', 'portal_dependency_level');

            $domainIds = $company->domains->pluck('id');

            $latestHpSnapshot = $domainIds->isEmpty()
                ? null
                : HpSnapshot::query()
                    ->with(['domain', 'fact', 'updateTargets.updateTarget'])
                    ->whereIn('domain_id', $domainIds)
                    ->orderByDesc('crawled_at')
                    ->orderByDesc('id')
                    ->first();

            $updateTargets = UpdateTarget::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $hpObservationAvailable = true;
        } catch (Throwable $e) {
            report($e);
            $hpObservationUnavailableReason = 'HP観測のDB状態を確認できなかったため、このセクションだけ一時停止中。通常のcompany詳細・4軸スコア・kill_flagsはそのまま使える。';
        }

        return view('companies.show', compact(
            'company',
            'scoreAxes',
            'scoresByAxis',
            'latestHpSnapshot',
            'updateTargets',
            'hpObservationOptions',
            'hpObservationAvailable',
            'hpObservationUnavailableReason',
            'hpObservationHasNoteColumn',
            'hpObservationHasPortalDependencyColumn',
        ));
    }

    public function createFromSource(SourceRecord $sourceRecord): View|RedirectResponse
    {
        $sourceRecord->load('sourceLink.company');

        if ($sourceRecord->sourceLink) {
            return redirect()
                ->route('companies.show', $sourceRecord->sourceLink->company)
                ->with('status', 'このsource_recordはすでにcompanyへリンク済み。');
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

        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'match_type' => ['required', 'in:manual_same'],
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

        return redirect()
            ->route('companies.show', $company)
            ->with('status', 'source_recordを既存companyへリンクした。');
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



    public function storeHpObservation(Request $request, Company $company): RedirectResponse
    {
        try {
            $requiredTables = ['domains', 'hp_snapshots', 'hp_facts', 'snapshot_update_targets', 'update_targets'];
            foreach ($requiredTables as $table) {
                if (!Schema::hasTable($table)) {
                    return redirect()
                        ->route('companies.show', $company)
                        ->withErrors(['hp_observation' => "HP観測に必要なDBテーブルが未作成: {$table}"]);
                }
            }
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('companies.show', $company)
                ->withErrors(['hp_observation' => 'HP観測のDB状態確認でエラー。通常のcompany詳細は使える状態に戻した。']);
        }

        $domainIds = $company->domains()->pluck('id')->all();

        if (empty($domainIds)) {
            return redirect()
                ->route('companies.show', $company)
                ->withErrors(['domain_id' => 'このcompanyにはdomainがないため、HP観測を保存できない。先にsource_recordからURL付きでcompany化して。']);
        }

        $validated = $request->validate([
            'domain_id' => ['required', 'integer', 'in:' . implode(',', $domainIds)],
            'requested_url' => ['nullable', 'string', 'max:2000'],
            'final_url' => ['nullable', 'string', 'max:2000'],
            'http_status' => ['nullable', 'integer', 'min:100', 'max:599'],
            'crawled_at' => ['nullable', 'date'],
            'ssl_enabled' => ['required', 'in:unknown,1,0'],
            'mobile_friendly' => ['required', 'in:unknown,1,0'],
            'update_status' => ['required', 'in:unknown,fresh,half_year_stale,one_year_stale,three_year_stale'],
            'contact_method_type' => ['required', 'in:unknown,good,weak,none'],
            'cms_type' => ['required', 'in:unknown,wordpress,wix,jimdo,static_html,other'],
            'footer_year_status' => ['required', 'in:unknown,current,old,missing'],
            'portal_dependency_level' => ['required', 'in:unknown,low,medium,high'],
            'has_contact_form' => ['required', 'in:unknown,1,0'],
            'has_sns_link' => ['required', 'in:unknown,1,0'],
            'has_portal_link' => ['required', 'in:unknown,1,0'],
            'has_reservation' => ['required', 'in:unknown,1,0'],
            'has_ec' => ['required', 'in:unknown,1,0'],
            'has_recruiting' => ['required', 'in:unknown,1,0'],
            'observation_note' => ['nullable', 'string', 'max:10000'],
            'targets' => ['nullable', 'array'],
            'targets.*.status' => ['nullable', 'in:unknown,not_present,present_active,present_stopped'],
            'targets.*.last_update_date' => ['nullable', 'date'],
            'targets.*.note' => ['nullable', 'string', 'max:2000'],
        ]);

        $domain = Domain::query()
            ->where('company_id', $company->id)
            ->findOrFail($validated['domain_id']);

        DB::transaction(function () use ($validated, $domain) {
            $snapshotPayload = [
                'domain_id' => $domain->id,
                'crawl_type' => 'manual_verify',
                'snapshot_version' => 'manual_v1',
                'requested_url' => $validated['requested_url'] ?: $domain->url,
                'final_url' => $validated['final_url'] ?: null,
                'http_status' => $validated['http_status'] ?? null,
                'crawled_at' => !empty($validated['crawled_at']) ? $validated['crawled_at'] : now(),
            ];

            if (Schema::hasColumn('hp_snapshots', 'observation_note')) {
                $snapshotPayload['observation_note'] = $validated['observation_note'] ?? null;
            }

            $snapshot = HpSnapshot::create($snapshotPayload);

            $targetInputs = $validated['targets'] ?? [];
            $targetStatuses = collect($targetInputs)->pluck('status')->filter();
            $hasAnyPresentTarget = $targetStatuses->contains(fn ($status) => in_array($status, ['present_active', 'present_stopped'], true));

            $hpFactPayload = [
                'hp_snapshot_id' => $snapshot->id,
                'has_ec' => $this->triStateBool($validated['has_ec']),
                'has_reservation' => $this->triStateBool($validated['has_reservation']),
                'has_recruiting' => $this->triStateBool($validated['has_recruiting']),
                'has_portal_link' => $this->triStateBool($validated['has_portal_link']),
                'has_sns_link' => $this->triStateBool($validated['has_sns_link']),
                'has_google_business_link' => null,
                'has_contact_form' => $this->triStateBool($validated['has_contact_form']),
                'has_public_email' => null,
                'has_phone' => null,
                'contact_method_type' => $validated['contact_method_type'],
                'update_status' => $validated['update_status'],
                'has_update_targets' => $targetStatuses->isEmpty() ? null : $hasAnyPresentTarget,
                'cms_type' => $validated['cms_type'],
                'builder_type' => in_array($validated['cms_type'], ['wix', 'jimdo'], true) ? $validated['cms_type'] : null,
                'mobile_friendly' => $this->triStateBool($validated['mobile_friendly']),
                'ssl_enabled' => $this->triStateBool($validated['ssl_enabled']),
                'footer_year_status' => $validated['footer_year_status'],
                'extractor_version' => 'manual_v1',
                'extracted_at' => now(),
            ];

            if (Schema::hasColumn('hp_facts', 'portal_dependency_level')) {
                $hpFactPayload['portal_dependency_level'] = $validated['portal_dependency_level'];
            }

            HpFact::create($hpFactPayload);

            foreach ($targetInputs as $updateTargetId => $targetInput) {
                $status = $targetInput['status'] ?? 'unknown';

                if ($status === 'unknown') {
                    continue;
                }

                [$isPresent, $isStopped] = match ($status) {
                    'not_present' => [false, false],
                    'present_active' => [true, false],
                    'present_stopped' => [true, true],
                    default => [null, null],
                };

                SnapshotUpdateTarget::create([
                    'hp_snapshot_id' => $snapshot->id,
                    'update_target_id' => (int) $updateTargetId,
                    'is_present' => $isPresent,
                    'is_stopped' => $isStopped,
                    'last_update_date' => $targetInput['last_update_date'] ?? null,
                    'evidence_json' => [
                        'basis' => 'manual',
                        'status' => $status,
                        'note' => trim((string) ($targetInput['note'] ?? '')) ?: null,
                    ],
                    'extractor_version' => 'manual_v1',
                ]);
            }
        });

        return redirect()
            ->route('companies.show', $company)
            ->with('status', 'HP観測データを保存した。次はこの観測を元に4軸スコア自動提案へ進める。');
    }


    public function storeScores(Request $request, Company $company): RedirectResponse
    {
        $axisKeys = array_keys($this->scoreAxisOptions());

        $validated = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*.value' => ['required', 'integer', 'min:0', 'max:5'],
            'scores.*.confidence' => ['required', 'in:0.3,0.6,0.9'],
            'scores.*.note' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach ($validated['scores'] as $axis => $scoreInput) {
            if (!in_array($axis, $axisKeys, true)) {
                continue;
            }

            $note = trim((string) ($scoreInput['note'] ?? ''));

            CompanyScore::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'axis' => $axis,
                    'algo_version' => 'v1',
                ],
                [
                    'value' => (int) $scoreInput['value'],
                    'confidence' => (string) $scoreInput['confidence'],
                    'auto_suggested_value' => null,
                    'reason_json' => [
                        'basis' => 'manual',
                        'drivers' => [],
                        'evidence' => [],
                        'note' => $note !== '' ? $note : null,
                    ],
                    'scored_by' => auth()->user()?->email ?? 'manual',
                    'scored_at' => now(),
                ]
            );
        }

        return redirect()
            ->route('companies.show', $company)
            ->with('status', '4軸スコアを保存した。');
    }


    private function triStateBool(string $value): ?bool
    {
        return match ($value) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    private function hpObservationOptions(): array
    {
        return [
            'tri_state' => [
                'unknown' => '不明',
                '1' => 'あり / はい',
                '0' => 'なし / いいえ',
            ],
            'mobile_friendly' => [
                'unknown' => '不明',
                '1' => 'スマホ対応あり',
                '0' => 'スマホ対応なし / 厳しい',
            ],
            'ssl_enabled' => [
                'unknown' => '不明',
                '1' => 'SSLあり',
                '0' => 'SSLなし',
            ],
            'update_status' => [
                'unknown' => '不明',
                'fresh' => '新しい / 動いている',
                'half_year_stale' => '半年以上止まり気味',
                'one_year_stale' => '1年以上止まり気味',
                'three_year_stale' => '3年以上止まり気味',
            ],
            'contact_method_type' => [
                'unknown' => '不明',
                'good' => '良い / 分かりやすい',
                'weak' => '微妙 / 弱い',
                'none' => 'なし / 見つからない',
            ],
            'cms_type' => [
                'unknown' => '不明',
                'wordpress' => 'WordPressっぽい',
                'wix' => 'Wixっぽい',
                'jimdo' => 'Jimdoっぽい',
                'static_html' => '静的HTMLっぽい',
                'other' => 'その他',
            ],
            'footer_year_status' => [
                'unknown' => '不明',
                'current' => '今年/最近',
                'old' => '古い年のまま',
                'missing' => '年表記なし',
            ],
            'portal_dependency_level' => [
                'unknown' => '不明',
                'low' => '低い',
                'medium' => '中くらい',
                'high' => '高い',
            ],
            'target_status' => [
                'unknown' => '不明 / 未確認',
                'not_present' => '存在しない',
                'present_active' => '存在する・動いている',
                'present_stopped' => '存在する・止まっている',
            ],
        ];
    }


    private function scoreJudgment(int $opportunityScore, int $riskScore, int $scoredAxesCount): array
    {
        if ($scoredAxesCount < 4) {
            return ['未採点あり', 'gray'];
        }

        if ($opportunityScore >= 7 && $riskScore <= 3) {
            return ['高機会・低リスク', 'green'];
        }

        if ($opportunityScore >= 7 && $riskScore >= 7) {
            return ['高機会・高リスク', 'blue'];
        }

        if ($opportunityScore <= 3 && $riskScore >= 7) {
            return ['低機会・高リスク', 'red'];
        }

        if ($opportunityScore <= 3 && $riskScore <= 3) {
            return ['低機会・低リスク', 'gray'];
        }

        return ['要確認', 'blue'];
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
                'label' => '開発・運用難易度リスク',
                'group' => 'リスク',
                'polarity' => '高いほど危険',
                'description' => '予約・決済・在庫・物件DB・規制対応など、MVPスコープを逸脱しやすいか。',
                'anchor_0' => '静的情報提供と簡単な更新で完結する。',
                'anchor_3' => '軽い予約・問い合わせ・会員要素などが一部ある。',
                'anchor_5' => '予約エンジン、決済、在庫/物件DB、強い広告規制などが中核。',
            ],
            'portal_dependence' => [
                'label' => 'ポータル・SNS依存リスク',
                'group' => 'リスク',
                'polarity' => '高いほど危険',
                'description' => '自社HPではなく、ポータル・SNS・Googleマップ等が実質的な集客窓口になっているか。',
                'anchor_0' => '自社HPが主な発信・集客窓口になっている。',
                'anchor_3' => '自社HPもあるが、SNSやポータルとの併用色が強い。',
                'anchor_5' => '自社HPが形骸化し、実質ポータルやSNSが窓口になっている。',
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
}
