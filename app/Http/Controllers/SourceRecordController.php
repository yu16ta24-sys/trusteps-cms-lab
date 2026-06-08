<?php

namespace App\Http\Controllers;

use App\Models\SourceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SourceRecordController extends Controller
{
    public function index(Request $request): View
    {
        $query = SourceRecord::query()->latest('id');

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

        $sourceRecords = $query->paginate(30)->withQueryString();

        $sourceTypes = SourceRecord::query()
            ->select('source_type')
            ->distinct()
            ->orderBy('source_type')
            ->pluck('source_type');

        $totalCount = SourceRecord::query()->count();

        return view('source_records.index', compact('sourceRecords', 'sourceTypes', 'totalCount'));
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
        }, 'source_records_import_template_v0.12.0.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request): RedirectResponse|View
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'max:10240'],
            'default_source_type' => ['required', 'string', 'max:80'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('csv_file');
        $dryRun = $request->boolean('dry_run');

        $result = $this->processCsvImport(
            file: $file,
            defaultSourceType: (string) $request->input('default_source_type'),
            shouldPersist: !$dryRun,
        );

        if ($dryRun) {
            return view('source_records.import', [
                'templateHeaders' => $this->csvTemplateHeaders(),
                'previewResult' => $result,
            ]);
        }

        return redirect()
            ->route('source-records.import')
            ->with('status', "CSV取り込み完了。登録 {$result['imported']} 件 / スキップ {$result['skipped']} 件。")
            ->with('import_summary', $result);
    }

    private function processCsvImport($file, string $defaultSourceType, bool $shouldPersist): array
    {
        $content = file_get_contents($file->getRealPath());

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
            'file_name' => $file->getClientOriginalName(),
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
                    'schema_version' => 'source_records_csv_v0.12.0',
                    'row_number' => $rowNumber,
                    'original_row' => $assoc,
                    'canonical' => $canonical,
                    'duplicate_signals_at_import' => $duplicateSignals,
                    'uploaded_file_name' => $file->getClientOriginalName(),
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
