<?php

namespace App\Http\Controllers;

use App\Models\SourceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

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
        return view('source_records.show', compact('sourceRecord'));
    }

    public function importForm(): View
    {
        return view('source_records.import');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'max:10240'],
            'default_source_type' => ['required', 'string', 'max:80'],
        ]);

        $file = $request->file('csv_file');
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
            return back()->withErrors(['csv_file' => 'CSVのヘッダー行を読み取れなかった。']);
        }

        $header = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        while (($row = fgetcsv($stream)) !== false) {
            if ($this->isEmptyCsvRow($row)) {
                continue;
            }

            $assoc = $this->combineCsvRow($header, $row);
            $canonical = $this->extractCanonicalFields($assoc, $request->input('default_source_type'));

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
                $skipped++;
                $errors[] = '会社名がない行をスキップした。';
                continue;
            }

            SourceRecord::create([
                'source_type' => $canonical['source_type'],
                'source_url' => $canonical['source_url'] ?? null,
                'raw_json' => [
                    'input_type' => 'csv',
                    'original_row' => $assoc,
                    'canonical' => $canonical,
                    'uploaded_file_name' => $file->getClientOriginalName(),
                ],
                'corporate_number' => $this->normalizeCorporateNumber($canonical['corporate_number'] ?? null),
                'normalized_domain' => $this->normalizeDomain($canonical['source_url'] ?? null),
                'normalized_phone' => $this->normalizePhone($canonical['phone'] ?? null),
                'name_norm' => $this->normalizeName($canonical['company_name']),
                'pref' => $canonical['pref'] ?? null,
                'city' => $canonical['city'] ?? null,
                'fetched_at' => now(),
            ]);

            $imported++;
        }

        fclose($stream);

        return redirect()
            ->route('source-records.index')
            ->with('status', "CSV取り込み完了。登録 {$imported} 件 / スキップ {$skipped} 件。");
    }

    private function extractCanonicalFields(array $assoc, string $defaultSourceType): array
    {
        return [
            'source_type' => $this->firstValue($assoc, ['source_type', '取得元', 'ソース', 'source']) ?: $defaultSourceType,
            'source_url' => $this->firstValue($assoc, ['source_url', 'url', 'website', 'hp_url', 'ホームページ', 'HP', 'ＨＰ', 'サイトURL']),
            'company_name' => $this->firstValue($assoc, ['company_name', 'name', 'display_name', '会社名', '名称', '屋号', '法人名']),
            'corporate_number' => $this->firstValue($assoc, ['corporate_number', '法人番号']),
            'phone' => $this->firstValue($assoc, ['phone', 'tel', 'telephone', '電話', '電話番号']),
            'pref' => $this->firstValue($assoc, ['pref', 'prefecture', '都道府県']),
            'city' => $this->firstValue($assoc, ['city', 'municipality', '市区町村', '市町村']),
        ];
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
