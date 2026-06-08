<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyKillFlag;
use App\Models\CompanyScore;
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
            ->with(['industry', 'municipality.prefecture', 'primaryDomain', 'mergedInto'])
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
            'mergedInto',
            'mergedChildren',
            'killFlags',
            'scores',
        ]);

        $scoreAxes = $this->scoreAxisOptions();
        $scoresByAxis = $company->scores
            ->where('algo_version', 'v1')
            ->keyBy('axis');

        return view('companies.show', compact('company', 'scoreAxes', 'scoresByAxis'));
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
