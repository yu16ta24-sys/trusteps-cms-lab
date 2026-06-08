<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanySourceLink;
use App\Models\Domain;
use App\Models\Industry;
use App\Models\Municipality;
use App\Models\SourceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $query = Company::query()
            ->with(['industry', 'municipality.prefecture', 'primaryDomain'])
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

        $companies = $query->paginate(30)->withQueryString();

        $industries = Industry::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $totalCount = Company::query()->count();

        return view('companies.index', compact('companies', 'industries', 'totalCount'));
    }

    public function show(Company $company): View
    {
        $company->load([
            'industry',
            'municipality.prefecture',
            'primaryDomain',
            'domains',
            'sourceLinks.sourceRecord',
        ]);

        return view('companies.show', compact('company'));
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
                'pref' => $validated['pref'] ?? null,
                'city' => $validated['city'] ?? null,
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
