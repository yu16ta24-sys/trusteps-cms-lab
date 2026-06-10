<?php

namespace App\Http\Controllers;

use App\Models\SourceRecord;
use App\Services\Discovery\DirectoryLinkExtractor;
use App\Services\Discovery\UrlCandidateClassifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiscoveryLabController extends Controller
{
    public function __construct(
        private readonly UrlCandidateClassifier $classifier,
        private readonly DirectoryLinkExtractor $directoryExtractor
    ) {
    }

    public function show(Request $request): View
    {
        $this->cleanupOldPreviews();

        return view('discovery.lab', [
            'preview' => null,
            'defaultSourceType' => 'discovery_lab_manual',
        ]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'urls' => ['required', 'string', 'max:200000'],
            'default_source_type' => ['nullable', 'string', 'max:80'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'pref' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'raw_industry' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:2000'],
        ]);

        $limit = (int) config('discovery.manual_url_limit', 500);
        $lines = $this->splitLines($validated['urls']);

        if (count($lines) > $limit) {
            return redirect()
                ->route('discovery.lab')
                ->withInput()
                ->withErrors(['urls' => "一度に処理できるURLは最大 {$limit} 件。件数を分けて投入して。"]);
        }

        $meta = [
            'default_source_type' => trim($validated['default_source_type'] ?? '') ?: 'discovery_lab_manual',
            'source_name' => trim($validated['source_name'] ?? ''),
            'pref' => trim($validated['pref'] ?? ''),
            'city' => trim($validated['city'] ?? ''),
            'raw_industry' => trim($validated['raw_industry'] ?? ''),
            'memo' => trim($validated['memo'] ?? ''),
            'created_at' => now()->timestamp,
        ];

        $classified = [];
        foreach ($lines as $lineNumber => $line) {
            $row = $this->classifier->classify($line);
            $row['row_id'] = count($classified);
            $row['line_number'] = $lineNumber + 1;
            $row['duplicate_signals'] = $this->duplicateSignals($row['normalized_url'], $row['normalized_domain']);
            $row['fanout_count'] = $row['normalized_domain'] ? SourceRecord::query()->where('normalized_domain', $row['normalized_domain'])->count() : 0;
            $row['high_fanout_warning'] = $this->hasHighFanoutWarning($row['normalized_domain'], $classified, $row['fanout_count']);

            if ($row['high_fanout_warning']) {
                $row['warnings'][] = '同一ドメイン候補が多い。ポータル/共有ドメイン/誤統合に注意。';
                $row['warnings'] = array_values(array_unique($row['warnings']));
            }

            $row['default_checked'] = $this->shouldDefaultCheck($row);
            $classified[] = $row;
        }

        $token = (string) Str::uuid();
        $preview = [
            'token' => $token,
            'meta' => $meta,
            'rows' => $classified,
            'summary' => $this->buildSummary($classified),
        ];

        session()->put("discovery_lab_previews.{$token}", $preview);

        return view('discovery.lab', [
            'preview' => $preview,
            'defaultSourceType' => $meta['default_source_type'],
        ]);
    }


    public function directoryPreview(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'directory_url' => ['required', 'string', 'max:2000'],
            'default_source_type' => ['nullable', 'string', 'max:80'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'pref' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'raw_industry' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:2000'],
        ]);

        $directoryUrl = trim($validated['directory_url']);
        $extraction = $this->directoryExtractor->extract($directoryUrl);

        if (!$extraction['ok']) {
            return redirect()
                ->route('discovery.lab')
                ->withInput()
                ->withErrors(['directory_url' => $extraction['error'] ?? '名簿URLからリンクを抽出できなかった。']);
        }

        $meta = [
            'input_type' => 'directory_link_extract',
            'default_source_type' => trim($validated['default_source_type'] ?? '') ?: 'discovery_lab_directory',
            'source_name' => trim($validated['source_name'] ?? '') ?: ($extraction['title'] ?? '候補収集ラボ 名簿URL抽出'),
            'source_page_url' => $extraction['source_url'] ?? $directoryUrl,
            'source_page_title' => $extraction['title'] ?? null,
            'pref' => trim($validated['pref'] ?? ''),
            'city' => trim($validated['city'] ?? ''),
            'raw_industry' => trim($validated['raw_industry'] ?? ''),
            'memo' => trim($validated['memo'] ?? ''),
            'created_at' => now()->timestamp,
            'fetch_warnings' => $extraction['warnings'] ?? [],
        ];

        $classified = [];
        foreach (($extraction['links'] ?? []) as $index => $link) {
            $row = $this->classifier->classify($link['url'] ?? '');
            $row['row_id'] = count($classified);
            $row['line_number'] = $index + 1;
            $row['link_text'] = $link['text'] ?? '';
            $row['link_context'] = $link['context'] ?? '';
            $row['source_page_url'] = $meta['source_page_url'];
            $row['discovery_method'] = 'directory_link_extract';
            $row['duplicate_signals'] = $this->duplicateSignals($row['normalized_url'], $row['normalized_domain']);
            $row['fanout_count'] = $row['normalized_domain'] ? SourceRecord::query()->where('normalized_domain', $row['normalized_domain'])->count() : 0;
            $row['high_fanout_warning'] = $this->hasHighFanoutWarning($row['normalized_domain'], $classified, $row['fanout_count']);

            if ($row['high_fanout_warning']) {
                $row['warnings'][] = '同一ドメイン候補が多い。ポータル/共有ドメイン/誤統合に注意。';
                $row['warnings'] = array_values(array_unique($row['warnings']));
            }

            $row['default_checked'] = $this->shouldDefaultCheck($row);
            $classified[] = $row;
        }

        $token = (string) Str::uuid();
        $preview = [
            'token' => $token,
            'meta' => $meta,
            'rows' => $classified,
            'summary' => $this->buildSummary($classified),
        ];

        session()->put("discovery_lab_previews.{$token}", $preview);

        return view('discovery.lab', [
            'preview' => $preview,
            'defaultSourceType' => $meta['default_source_type'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preview_token' => ['required', 'string'],
            'selected_rows' => ['required', 'array', 'min:1'],
            'selected_rows.*' => ['integer'],
        ]);

        $preview = session("discovery_lab_previews.{$validated['preview_token']}");

        if (!$preview) {
            return redirect()
                ->route('discovery.lab')
                ->withErrors(['preview_token' => 'プレビュー情報が見つからなかった。もう一度URLリストをプレビューして。']);
        }

        $selectedIds = collect($validated['selected_rows'])->map(fn ($value) => (int) $value)->flip();
        $rows = collect($preview['rows'] ?? [])
            ->filter(fn ($row) => $selectedIds->has((int) $row['row_id']))
            ->values();

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $preview, &$imported, &$skipped) {
            foreach ($rows as $row) {
                if (empty($row['is_valid_url']) || empty($row['normalized_url'])) {
                    $skipped++;
                    continue;
                }

                SourceRecord::create($this->buildSourceRecordPayload($row, $preview['meta'] ?? []));
                $imported++;
            }
        });

        session()->forget("discovery_lab_previews.{$validated['preview_token']}");

        return redirect()
            ->route('source-records.index', ['source_type' => $preview['meta']['default_source_type'] ?? 'discovery_lab_manual'])
            ->with('status', "候補収集ラボからsource_recordsへ保存した。登録 {$imported} 件 / スキップ {$skipped} 件。companyは自動作成していない。");
    }

    public function exportCsv(Request $request): StreamedResponse|RedirectResponse
    {
        $validated = $request->validate([
            'preview_token' => ['required', 'string'],
        ]);

        $preview = session("discovery_lab_previews.{$validated['preview_token']}");

        if (!$preview) {
            return redirect()
                ->route('discovery.lab')
                ->withErrors(['preview_token' => 'プレビュー情報が見つからなかった。もう一度URLリストをプレビューして。']);
        }

        $headers = [
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

        $rows = collect($preview['rows'] ?? [])->filter(fn ($row) => !empty($row['is_valid_url']))->values();
        $meta = $preview['meta'] ?? [];

        return response()->streamDownload(function () use ($headers, $rows, $meta) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $meta['default_source_type'] ?? 'discovery_lab_manual',
                    $meta['source_name'] ?: '候補収集ラボ 手動URLリスト',
                    $meta['source_page_url'] ?? '',
                    $row['link_text'] ?: ($row['normalized_domain'] ?? $row['raw_url'] ?? ''),
                    '',
                    '',
                    $row['normalized_url'] ?? $row['raw_url'] ?? '',
                    $meta['raw_industry'] ?? '',
                    $meta['pref'] ?? '',
                    $meta['city'] ?? '',
                    now()->toDateString(),
                    $this->csvMemo($row, $meta),
                ]);
            }

            fclose($handle);
        }, 'discovery_lab_candidates_v0.18.2.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function splitLines(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];

        return collect($lines)
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn ($line) => $line !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function duplicateSignals(?string $normalizedUrl, ?string $normalizedDomain): array
    {
        $signals = [];

        if ($normalizedUrl) {
            $urlCount = SourceRecord::query()->where('source_url', $normalizedUrl)->count();
            if ($urlCount > 0) {
                $signals[] = "source_url一致 {$urlCount}件";
            }
        }

        if ($normalizedDomain) {
            $domainCount = SourceRecord::query()->where('normalized_domain', $normalizedDomain)->count();
            if ($domainCount > 0) {
                $signals[] = "domain一致 {$domainCount}件";
            }
        }

        return $signals;
    }

    private function hasHighFanoutWarning(?string $domain, array $existingRows, int $existingCount): bool
    {
        if (!$domain) {
            return false;
        }

        $sameInPreview = collect($existingRows)
            ->filter(fn ($row) => ($row['normalized_domain'] ?? null) === $domain)
            ->count() + 1;

        return ($existingCount + $sameInPreview) >= (int) config('discovery.high_fanout_threshold', 5);
    }

    private function shouldDefaultCheck(array $row): bool
    {
        if (empty($row['is_valid_url'])) {
            return false;
        }

        return in_array($row['classification'], [
            'official_site_candidate',
            'builder_site_candidate',
        ], true);
    }

    private function buildSummary(array $rows): array
    {
        $collection = collect($rows);

        return [
            'total' => $collection->count(),
            'valid' => $collection->where('is_valid_url', true)->count(),
            'invalid' => $collection->where('is_valid_url', false)->count(),
            'default_checked' => $collection->where('default_checked', true)->count(),
            'duplicate' => $collection->filter(fn ($row) => !empty($row['duplicate_signals']))->count(),
            'high_fanout' => $collection->where('high_fanout_warning', true)->count(),
            'by_classification' => $collection
                ->groupBy('classification')
                ->map(fn ($items) => $items->count())
                ->sortKeys()
                ->all(),
        ];
    }

    private function buildSourceRecordPayload(array $row, array $meta): array
    {
        $rawIndustry = $meta['raw_industry'] ?? null;
        $pref = $meta['pref'] ?? null;
        $city = $meta['city'] ?? null;
        $inputType = $meta['input_type'] ?? 'manual_url_list';
        $isDirectory = $inputType === 'directory_link_extract';
        $sourceName = $meta['source_name'] ?: ($isDirectory ? '候補収集ラボ 名簿URL抽出' : '候補収集ラボ 手動URLリスト');
        $sourceType = $meta['default_source_type'] ?: ($isDirectory ? 'discovery_lab_directory' : 'discovery_lab_manual');
        $displayName = trim((string) ($row['link_text'] ?? '')) ?: ($row['normalized_domain'] ?? $row['raw_url'] ?? 'discovery candidate');

        $raw = [
            'origin' => $isDirectory ? 'organization_list' : 'discovery_lab',
            'input_type' => $inputType,
            'source_name' => $sourceName,
            'source_page_url' => $meta['source_page_url'] ?? null,
            'source_page_title' => $meta['source_page_title'] ?? null,
            'discovery_method' => $isDirectory ? 'directory_link_extract' : 'manual_url_list',
            'no_http_fetch' => !$isDirectory,
            'http_fetch_scope' => $isDirectory ? 'directory_page_only' : null,
            'link_text' => $row['link_text'] ?? null,
            'link_context' => $row['link_context'] ?? null,
            'raw_url' => $row['raw_url'] ?? null,
            'normalized_url' => $row['normalized_url'] ?? null,
            'normalized_domain' => $row['normalized_domain'] ?? null,
            'url_classification' => $row['classification'] ?? 'unknown',
            'classification_label' => $row['classification_label'] ?? '不明',
            'confidence' => $row['confidence'] ?? 0,
            'warnings' => $row['warnings'] ?? [],
            'duplicate_signals' => $row['duplicate_signals'] ?? [],
            'high_fanout_warning' => $row['high_fanout_warning'] ?? false,
            'fanout_count_at_preview' => $row['fanout_count'] ?? 0,
            'pref' => $pref ?: null,
            'city' => $city ?: null,
            'raw_industry' => $rawIndustry ?: null,
            'memo' => $meta['memo'] ?? null,
            'fetch_warnings' => $meta['fetch_warnings'] ?? [],
            'created_from' => 'discovery_lab v0.18.2 ' . $inputType,
            'canonical' => [
                'company_name' => $displayName,
                'source_url' => $row['normalized_url'] ?? null,
                'raw_industry' => $rawIndustry ?: null,
                'pref' => $pref ?: null,
                'city' => $city ?: null,
            ],
        ];

        return [
            'source_type' => $sourceType,
            'source_url' => $row['normalized_url'] ?? null,
            'raw_json' => $raw,
            'corporate_number' => null,
            'normalized_domain' => $row['normalized_domain'] ?? null,
            'normalized_phone' => null,
            'name_norm' => $this->normalizeName($displayName),
            'pref' => $pref ?: null,
            'city' => $city ?: null,
            'fetched_at' => now(),
        ];
    }

    private function csvMemo(array $row, array $meta): string
    {
        $parts = [
            'discovery_lab_v0.18.2',
            'classification=' . ($row['classification'] ?? 'unknown'),
            'confidence=' . ($row['confidence'] ?? 0),
        ];

        if (!empty($row['warnings'])) {
            $parts[] = 'warnings=' . implode(' / ', $row['warnings']);
        }

        if (!empty($meta['memo'])) {
            $parts[] = 'memo=' . $meta['memo'];
        }

        return implode(' | ', $parts);
    }

    private function cleanupOldPreviews(): void
    {
        foreach ((array) session('discovery_lab_previews', []) as $token => $preview) {
            $createdAt = (int) data_get($preview, 'meta.created_at', 0);
            if ($createdAt > 0 && now()->timestamp - $createdAt > 7200) {
                session()->forget("discovery_lab_previews.{$token}");
            }
        }
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
