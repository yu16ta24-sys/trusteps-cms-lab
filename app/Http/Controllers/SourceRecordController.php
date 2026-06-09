<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanySourceLink;
use App\Models\Domain;
use App\Models\Municipality;
use App\Models\SourceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SourceRecordController extends Controller
{
    public function index(Request $request): View
    {
        $query = SourceRecord::query()
            ->with('sourceLink');

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($q) {
                $inner
                    ->where('source_url', 'like', "%{$q}%")
                    ->orWhere('name_norm', 'like', "%{$q}%")
                    ->orWhere('pref', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('corporate_number', 'like', "%{$q}%")
                    ->orWhere('normalized_domain', 'like', "%{$q}%");
            });
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        if ($request->filled('pref')) {
            $query->where('pref', $request->input('pref'));
        }

        if ($request->filled('city')) {
            $query->where('city', $request->input('city'));
        }

        if ($request->filled('raw_industry')) {
            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.canonical.raw_industry')) = ?",
                [$request->input('raw_industry')]
            );
        }

        // Processing queue helpers are computed before applying link_status,
        // so they respect search/source/pref/city/industry filters but can still point to
        // the next unlinked record even when the current list is showing linked records.
        $unlinkedQueueQuery = clone $query;
        $unlinkedQueueCount = (clone $unlinkedQueueQuery)
            ->whereDoesntHave('sourceLink')
            ->count();
        $nextUnlinkedSourceRecord = (clone $unlinkedQueueQuery)
            ->whereDoesntHave('sourceLink')
            ->orderBy('id')
            ->first();

        if ($request->filled('link_status')) {
            if ($request->input('link_status') === 'linked') {
                $query->whereHas('sourceLink');
            } elseif ($request->input('link_status') === 'unlinked') {
                $query->whereDoesntHave('sourceLink');
            }
        }

        $sort = (string) $request->input('sort', 'id');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = [
            'id',
            'source_type',
            'name_norm',
            'normalized_domain',
            'pref_city',
            'fetched_at',
        ];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        if ($sort === 'pref_city') {
            $query
                ->orderBy('pref', $direction)
                ->orderBy('city', $direction)
                ->orderBy('id', 'desc');
        } else {
            $query
                ->orderBy($sort, $direction)
                ->orderBy('id', 'desc');
        }

        $sourceRecords = $query->paginate(30)->withQueryString();

        $sourceTypes = SourceRecord::query()
            ->select('source_type')
            ->distinct()
            ->orderBy('source_type')
            ->pluck('source_type');

        $prefOptions = SourceRecord::query()
            ->whereNotNull('pref')
            ->where('pref', '!=', '')
            ->select('pref')
            ->distinct()
            ->orderBy('pref')
            ->pluck('pref');

        $cityOptions = SourceRecord::query()
            ->when($request->filled('pref'), fn ($cityQuery) => $cityQuery->where('pref', $request->input('pref')))
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->select('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');

        $rawIndustryOptions = SourceRecord::query()
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.canonical.raw_industry')) AS raw_industry")
            ->whereRaw("JSON_EXTRACT(raw_json, '$.canonical.raw_industry') IS NOT NULL")
            ->groupBy('raw_industry')
            ->orderBy('raw_industry')
            ->pluck('raw_industry')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->values();

        $totalCount = SourceRecord::query()->count();

        return view('source_records.index', compact(
            'sourceRecords',
            'sourceTypes',
            'prefOptions',
            'cityOptions',
            'rawIndustryOptions',
            'totalCount',
            'sort',
            'direction',
            'unlinkedQueueCount',
            'nextUnlinkedSourceRecord'
        ));
    }

    public function bulkCreateCompanies(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_record_ids' => ['required', 'array', 'min:1', 'max:100'],
            'source_record_ids.*' => ['integer', 'exists:source_records,id'],
        ]);

        $records = SourceRecord::query()
            ->with('sourceLink')
            ->whereIn('id', $validated['source_record_ids'])
            ->orderBy('id')
            ->get();

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($records, &$created, &$skipped) {
            foreach ($records as $record) {
                if ($record->sourceLink) {
                    $skipped++;
                    continue;
                }

                $defaults = $this->defaultsFromSourceRecord($record);

                $company = Company::create([
                    'status' => 'candidate',
                    'municipality_id' => $defaults['municipality_id'],
                    'industry_id' => null,
                    'primary_domain_id' => null,
                    'legal_name' => null,
                    'display_name' => $defaults['display_name'],
                    'name_norm' => $this->normalizeName($defaults['display_name']),
                    'alias_names_json' => null,
                    'corporate_number' => $this->normalizeCorporateNumber($record->corporate_number),
                    'pref' => $defaults['municipality_id'] ? null : $record->pref,
                    'city' => $defaults['municipality_id'] ? null : $record->city,
                    'is_killed' => false,
                ]);

                if ($record->source_url) {
                    $domain = Domain::create([
                        'company_id' => $company->id,
                        'url' => $record->source_url,
                        'normalized_domain' => $this->normalizeDomain($record->source_url),
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
                    'source_record_id' => $record->id,
                    'match_type' => 'manual_bulk_new',
                    'match_confidence' => 1.00,
                    'created_by' => auth()->user()?->email ?? 'manual',
                ]);

                $created++;
            }
        });

        return redirect()
            ->route('source-records.index', $request->except(['source_record_ids', '_token']))
            ->with('status', "一括company化完了。作成 {$created} 件 / スキップ {$skipped} 件。リンク済みsource_recordは安全のためスキップした。");
    }

    public function create(): View
    {
        return view('source_records.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_type' => ['required', 'string', 'max:80'],
            'source_url' => ['nullable', 'string', 'max:2000'],
            'company_name' => ['required', 'string', 'max:255'],
            'corporate_number' => ['nullable', 'string', 'max:13'],
            'phone' => ['nullable', 'string', 'max:50'],
            'pref' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:5000'],
        ]);

        $raw = [
            'input_type' => 'manual',
            'company_name' => $validated['company_name'],
            'source_url' => $validated['source_url'] ?? null,
            'corporate_number' => $validated['corporate_number'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'pref' => $validated['pref'] ?? null,
            'city' => $validated['city'] ?? null,
            'memo' => $validated['memo'] ?? null,
            'created_from' => 'source_records manual form',
        ];

        SourceRecord::create([
            'source_type' => $validated['source_type'],
            'source_url' => $validated['source_url'] ?? null,
            'raw_json' => $raw,
            'corporate_number' => $this->normalizeCorporateNumber($validated['corporate_number'] ?? null),
            'normalized_domain' => $this->normalizeDomain($validated['source_url'] ?? null),
            'normalized_phone' => $this->normalizePhone($validated['phone'] ?? null),
            'name_norm' => $this->normalizeName($validated['company_name']),
            'pref' => $validated['pref'] ?? null,
            'city' => $validated['city'] ?? null,
            'fetched_at' => now(),
        ]);

        return redirect()
            ->route('source-records.index')
            ->with('status', 'source_recordを1件登録した。');
    }

    public function show(SourceRecord $sourceRecord): View
    {
        $sourceRecord->load('sourceLink.company');

        return view('source_records.show', compact('sourceRecord'));
    }

    public function importForm(): View
    {
        $this->cleanupOldCsvImportPreviews();

        return view('source_records.import', [
            'templateHeaders' => $this->csvTemplateHeaders(),
        ]);
    }

    public function importTemplate(): StreamedResponse
    {
        $headers = $this->csvTemplateHeaders();

        return response()->streamDownload(function () use ($headers) {
            $handle = fopen('php://output', 'w');

            // Excelで開いたときの文字化け対策。
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            fputcsv($handle, [
                'public_list',
                '長野県 建設業者名簿',
                'https://example.jp/list-page',
                'サンプル工務店株式会社',
                '長野県松本市サンプル1-2-3',
                '0263-00-0000',
                'https://example-koumuten.jp',
                '建設業',
                '長野県',
                '松本市',
                now()->toDateString(),
                'Phase1テスト用サンプル',
            ]);

            fclose($handle);
        }, 'source_records_import_template_v0.12.1.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request): RedirectResponse|View
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'max:10240'],
            'default_source_type' => ['required', 'string', 'max:80'],
        ]);

        $this->cleanupOldCsvImportPreviews();

        $file = $request->file('csv_file');
        $defaultSourceType = (string) $request->input('default_source_type');
        $token = (string) Str::uuid();
        $previewDir = $this->csvImportPreviewDirectory();

        File::ensureDirectoryExists($previewDir);

        $storedPath = $previewDir . DIRECTORY_SEPARATOR . $token . '.csv';
        $originalFileName = $file->getClientOriginalName();
        $file->move($previewDir, $token . '.csv');

        session()->put("source_record_imports.{$token}", [
            'path' => $storedPath,
            'original_file_name' => $originalFileName,
            'default_source_type' => $defaultSourceType,
            'created_at' => now()->timestamp,
        ]);

        $result = $this->processCsvImport(
            filePath: $storedPath,
            originalFileName: $originalFileName,
            defaultSourceType: $defaultSourceType,
            shouldPersist: false,
        );

        $result['confirm_token'] = $token;
        $result['default_source_type'] = $defaultSourceType;

        return view('source_records.import', [
            'templateHeaders' => $this->csvTemplateHeaders(),
            'previewResult' => $result,
        ]);
    }

    public function confirmImport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'import_token' => ['required', 'string'],
        ]);

        $token = $validated['import_token'];
        $preview = session("source_record_imports.{$token}");

        if (!$preview || empty($preview['path']) || !is_file($preview['path'])) {
            session()->forget("source_record_imports.{$token}");

            return redirect()
                ->route('source-records.import')
                ->withErrors(['csv_file' => 'プレビュー済みCSVが見つからなかった。もう一度CSVを選択してプレビューして。']);
        }

        $result = $this->processCsvImport(
            filePath: $preview['path'],
            originalFileName: $preview['original_file_name'] ?? 'CSV',
            defaultSourceType: $preview['default_source_type'] ?? 'csv_import',
            shouldPersist: true,
        );

        @unlink($preview['path']);
        session()->forget("source_record_imports.{$token}");

        return redirect()
            ->route('source-records.import')
            ->with('status', "CSV取り込み完了。登録 {$result['imported']} 件 / スキップ {$result['skipped']} 件。")
            ->with('import_summary', $result);
    }

    public function cancelImport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'import_token' => ['required', 'string'],
        ]);

        $token = $validated['import_token'];
        $preview = session("source_record_imports.{$token}");

        if ($preview && !empty($preview['path']) && is_file($preview['path'])) {
            @unlink($preview['path']);
        }

        session()->forget("source_record_imports.{$token}");

        return redirect()
            ->route('source-records.import')
            ->with('status', 'CSV取り込みをキャンセルした。DBには登録していない。');
    }

    private function processCsvImport(string $filePath, string $originalFileName, string $defaultSourceType, bool $shouldPersist): array
    {
        $content = file_get_contents($filePath);

        $encoding = mb_detect_encoding($content, ['UTF-8', 'SJIS-win', 'CP932', 'EUC-JP'], true) ?: 'UTF-8';
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream);
        if (!$header) {
            fclose($stream);

            return $this->emptyImportResult([
                'CSVのヘッダー行を読み取れなかった。',
            ]);
        }

        $header = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);

        $result = [
            'mode' => $shouldPersist ? 'import' : 'preview',
            'file_name' => $originalFileName,
            'detected_encoding' => $encoding,
            'header' => $header,
            'total_rows' => 0,
            'imported' => 0,
            'valid_rows' => 0,
            'skipped' => 0,
            'with_domain' => 0,
            'with_phone' => 0,
            'without_url' => 0,
            'duplicate_hints' => 0,
            'warnings' => [],
            'errors' => [],
            'samples' => [],
        ];

        $rowNumber = 1;

        while (($row = fgetcsv($stream)) !== false) {
            $rowNumber++;

            if ($this->isEmptyCsvRow($row)) {
                continue;
            }

            $result['total_rows']++;

            $assoc = $this->combineCsvRow($header, $row);
            $canonical = $this->extractCanonicalFields($assoc, $defaultSourceType);
            $normalizedCorporateNumber = $this->normalizeCorporateNumber($canonical['corporate_number'] ?? null);
            $normalizedDomain = $this->normalizeDomain($canonical['source_url'] ?? null);
            $normalizedPhone = $this->normalizePhone($canonical['phone'] ?? null);
            $nameNorm = $this->normalizeName($canonical['company_name'] ?? null);
            $fetchedAt = $this->parseFetchedAt($canonical['fetched_at'] ?? null, $rowNumber, $result['warnings']);

            $validator = Validator::make($canonical, [
                'source_type' => ['required', 'string', 'max:80'],
                'source_url' => ['nullable', 'string', 'max:2000'],
                'company_name' => ['required', 'string', 'max:255'],
                'corporate_number' => ['nullable', 'string', 'max:13'],
                'phone' => ['nullable', 'string', 'max:50'],
                'pref' => ['nullable', 'string', 'max:50'],
                'city' => ['nullable', 'string', 'max:100'],
            ]);

            if ($validator->fails()) {
                $result['skipped']++;
                $result['errors'][] = "{$rowNumber}行目：会社名がない、または項目が長すぎるためスキップ。";
                continue;
            }

            $duplicateSignals = $this->duplicateSignals($normalizedCorporateNumber, $normalizedDomain, $nameNorm);
            if ($duplicateSignals !== []) {
                $result['duplicate_hints']++;
            }

            if ($normalizedDomain) {
                $result['with_domain']++;
            } else {
                $result['without_url']++;
            }

            if ($normalizedPhone) {
                $result['with_phone']++;
            }

            $result['valid_rows']++;

            if (count($result['samples']) < 8) {
                $result['samples'][] = [
                    'row_number' => $rowNumber,
                    'company_name' => $canonical['company_name'],
                    'source_type' => $canonical['source_type'],
                    'source_url' => $canonical['source_url'],
                    'source_name' => $canonical['source_name'],
                    'source_page_url' => $canonical['source_page_url'],
                    'pref' => $canonical['pref'],
                    'city' => $canonical['city'],
                    'normalized_domain' => $normalizedDomain,
                    'duplicate_signals' => $duplicateSignals,
                ];
            }

            if (!$shouldPersist) {
                continue;
            }

            SourceRecord::create([
                'source_type' => $canonical['source_type'],
                'source_url' => $canonical['source_url'] ?? null,
                'raw_json' => [
                    'input_type' => 'csv',
                    'schema_version' => 'source_records_csv_v0.12.1',
                    'row_number' => $rowNumber,
                    'original_row' => $assoc,
                    'canonical' => $canonical,
                    'duplicate_signals_at_import' => $duplicateSignals,
                    'uploaded_file_name' => $originalFileName,
                ],
                'corporate_number' => $normalizedCorporateNumber,
                'normalized_domain' => $normalizedDomain,
                'normalized_phone' => $normalizedPhone,
                'name_norm' => $nameNorm,
                'pref' => $canonical['pref'] ?? null,
                'city' => $canonical['city'] ?? null,
                'fetched_at' => $fetchedAt,
            ]);

            $result['imported']++;
        }

        fclose($stream);

        $result['warnings'] = array_slice(array_values(array_unique($result['warnings'])), 0, 30);
        $result['errors'] = array_slice($result['errors'], 0, 50);

        return $result;
    }

    private function csvImportPreviewDirectory(): string
    {
        return storage_path('app/source_record_import_previews');
    }

    private function cleanupOldCsvImportPreviews(): void
    {
        $expiresAt = time() - (60 * 60 * 2);
        $dir = $this->csvImportPreviewDirectory();

        if (is_dir($dir)) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*.csv') ?: [] as $path) {
                if (is_file($path) && filemtime($path) < $expiresAt) {
                    @unlink($path);
                }
            }
        }

        foreach ((array) session('source_record_imports', []) as $token => $meta) {
            if (($meta['created_at'] ?? 0) < $expiresAt) {
                if (!empty($meta['path']) && is_file($meta['path'])) {
                    @unlink($meta['path']);
                }

                session()->forget("source_record_imports.{$token}");
            }
        }
    }

    private function emptyImportResult(array $errors): array
    {
        return [
            'mode' => 'preview',
            'file_name' => null,
            'detected_encoding' => null,
            'header' => [],
            'total_rows' => 0,
            'imported' => 0,
            'valid_rows' => 0,
            'skipped' => 0,
            'with_domain' => 0,
            'with_phone' => 0,
            'without_url' => 0,
            'duplicate_hints' => 0,
            'warnings' => [],
            'errors' => $errors,
            'samples' => [],
        ];
    }

    private function extractCanonicalFields(array $assoc, string $defaultSourceType): array
    {
        return [
            'source_type' => $this->firstValue($assoc, ['source_type', '取得元種別', 'ソース種別', 'source']) ?: $defaultSourceType,
            'source_name' => $this->firstValue($assoc, ['source_name', '取得元名', 'ソース名', '名簿名', 'source_title']),
            'source_page_url' => $this->firstValue($assoc, ['source_page_url', 'list_url', '取得元URL', '取得元ページ', '名簿URL', '掲載元URL']),
            'source_url' => $this->firstValue($assoc, ['company_url', 'raw_url', 'website', 'website_url', 'hp_url', 'ホームページ', 'HP', 'ＨＰ', 'サイトURL', 'url', 'source_url']),
            'company_name' => $this->firstValue($assoc, ['company_name', 'raw_name', 'name', 'display_name', '会社名', '名称', '屋号', '法人名', '事業者名']),
            'raw_address' => $this->firstValue($assoc, ['raw_address', 'address', '住所', '所在地']),
            'corporate_number' => $this->firstValue($assoc, ['corporate_number', '法人番号']),
            'phone' => $this->firstValue($assoc, ['phone', 'raw_phone', 'tel', 'telephone', '電話', '電話番号']),
            'raw_industry' => $this->firstValue($assoc, ['raw_industry', 'industry', '業種', '業種名']),
            'pref' => $this->firstValue($assoc, ['pref', 'prefecture', '都道府県']),
            'city' => $this->firstValue($assoc, ['city', 'municipality', '市区町村', '市町村']),
            'fetched_at' => $this->firstValue($assoc, ['fetched_at', '取得日', '取得日時', '調査日']),
            'memo' => $this->firstValue($assoc, ['memo', 'note', 'メモ', '備考']),
        ];
    }

    private function csvTemplateHeaders(): array
    {
        return [
            'source_type',
            'source_name',
            'source_page_url',
            'raw_name',
            'raw_address',
            'raw_phone',
            'raw_url',
            'raw_industry',
            'pref',
            'city',
            'fetched_at',
            'memo',
        ];
    }

    private function parseFetchedAt(?string $value, int $rowNumber, array &$warnings): Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return now();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            $warnings[] = "{$rowNumber}行目：fetched_atを日付として読めなかったため、現在時刻で保存。";
            return now();
        }
    }

    private function duplicateSignals(?string $corporateNumber, ?string $normalizedDomain, ?string $nameNorm): array
    {
        $signals = [];

        if ($corporateNumber) {
            $count = SourceRecord::query()
                ->where('corporate_number', $corporateNumber)
                ->count();

            if ($count > 0) {
                $signals[] = "法人番号一致 {$count}件";
            }
        }

        if ($normalizedDomain && $nameNorm) {
            $count = SourceRecord::query()
                ->where('normalized_domain', $normalizedDomain)
                ->where('name_norm', $nameNorm)
                ->count();

            if ($count > 0) {
                $signals[] = "domain+name一致 {$count}件";
            }
        } elseif ($normalizedDomain) {
            $count = SourceRecord::query()
                ->where('normalized_domain', $normalizedDomain)
                ->count();

            if ($count > 0) {
                $signals[] = "domain一致 {$count}件";
            }
        }

        return $signals;
    }

    private function firstValue(array $assoc, array $keys): ?string
    {
        foreach ($keys as $key) {
            $normalizedKey = $this->normalizeHeader($key);
            if (array_key_exists($normalizedKey, $assoc)) {
                $value = trim((string) $assoc[$normalizedKey]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function combineCsvRow(array $header, array $row): array
    {
        $assoc = [];

        foreach ($header as $index => $key) {
            if ($key === '') {
                continue;
            }

            $assoc[$key] = $row[$index] ?? null;
        }

        return $assoc;
    }

    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        $value = mb_convert_kana($value, 'asKV', 'UTF-8');
        return $value;
    }

    private function defaultsFromSourceRecord(SourceRecord $sourceRecord): array
    {
        $raw = $sourceRecord->raw_json ?? [];
        $canonical = $raw['canonical'] ?? [];

        $companyName =
            $canonical['company_name']
            ?? $raw['company_name']
            ?? $sourceRecord->name_norm
            ?? "source_record #{$sourceRecord->id}";

        return [
            'display_name' => $companyName,
            'municipality_id' => $this->guessMunicipalityId($sourceRecord->pref, $sourceRecord->city),
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

    private function normalizePhone(?string $value): ?string
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
