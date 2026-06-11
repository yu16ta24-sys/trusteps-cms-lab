<?php

namespace App\Http\Controllers;

use App\Models\SourceRecord;
use App\Services\Discovery\DirectorySourceCollectorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DirectorySourceLabController extends Controller
{
    public function __construct(
        private readonly DirectorySourceCollectorService $collector
    ) {
    }

    public function show(): View
    {
        $this->cleanupOldPreviews();

        return view('directory-sources.lab', [
            'preview' => null,
        ]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'entry_urls' => ['required', 'string', 'max:50000'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'pref' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:2000'],
        ], [
            'entry_urls.required' => '名簿元を探す入口URLを1件以上入力してからプレビューして。',
            'entry_urls.max' => '入口URLリストが長すぎる。件数を分けて投入して。',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('directory-sources.lab')
                ->withInput()
                ->withErrors($validator);
        }

        $validated = $validator->validated();
        $limit = (int) config('discovery.directory_source_entry_url_limit', 10);
        $entryUrls = $this->splitLines((string) $validated['entry_urls']);

        if (count($entryUrls) > $limit) {
            return redirect()
                ->route('directory-sources.lab')
                ->withInput()
                ->withErrors(['entry_urls' => "一度に探索できる入口URLは最大 {$limit} 件。件数を分けて投入して。"]);
        }

        $meta = [
            'source_name' => trim($validated['source_name'] ?? '') ?: 'Directory Source Lab',
            'pref' => trim($validated['pref'] ?? ''),
            'city' => trim($validated['city'] ?? ''),
            'memo' => trim($validated['memo'] ?? ''),
            'created_at' => now()->timestamp,
        ];

        $result = $this->collector->collectMany($entryUrls);
        $rows = $result['rows'] ?? [];
        $token = (string) Str::uuid();
        $preview = [
            'token' => $token,
            'meta' => $meta,
            'entry_results' => $result['entry_results'] ?? [],
            'rows' => $rows,
            'excluded' => $result['excluded'] ?? [],
            'summary' => $this->buildSummary($rows, $result['entry_results'] ?? [], $result['excluded'] ?? []),
        ];

        session()->put("directory_source_lab_previews.{$token}", $preview);

        return view('directory-sources.lab', [
            'preview' => $preview,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $token = (string) $request->input('token', '');
        $selectedRows = array_map('intval', (array) $request->input('selected_rows', []));
        $preview = session()->get("directory_source_lab_previews.{$token}");

        if (!$preview || !is_array($preview)) {
            return redirect()
                ->route('directory-sources.lab')
                ->withErrors(['token' => 'プレビュー情報が見つからない。もう一度プレビューして。']);
        }

        if (empty($selectedRows)) {
            return redirect()
                ->route('directory-sources.lab')
                ->withErrors(['selected_rows' => '保存する名簿元候補を1件以上選択して。']);
        }

        $meta = $preview['meta'] ?? [];
        $rows = collect($preview['rows'] ?? [])
            ->filter(fn ($row) => in_array((int) ($row['row_id'] ?? -1), $selectedRows, true))
            ->values();

        if ($rows->isEmpty()) {
            return redirect()
                ->route('directory-sources.lab')
                ->withErrors(['selected_rows' => '選択された候補がプレビュー内に見つからない。']);
        }

        $saved = 0;

        DB::transaction(function () use ($rows, $meta, &$saved): void {
            foreach ($rows as $row) {
                SourceRecord::create([
                    'source_type' => 'directory_source_candidate',
                    'source_url' => $row['url'] ?? null,
                    'raw_json' => [
                        'collector_version' => '0.18.7',
                        'collector_type' => 'directory_source_lab',
                        'source_name' => $meta['source_name'] ?? null,
                        'memo' => $meta['memo'] ?? null,
                        'entry_url' => $row['entry_url'] ?? null,
                        'entry_title' => $row['entry_title'] ?? null,
                        'url' => $row['url'] ?? null,
                        'link_text' => $row['link_text'] ?? null,
                        'around_text' => $row['around_text'] ?? null,
                        'category_key' => $row['category_key'] ?? null,
                        'category_label' => $row['category_label'] ?? null,
                        'source_role' => $row['source_role'] ?? null,
                        'source_role_label' => $row['source_role_label'] ?? null,
                        'confidence_label' => $row['confidence_label'] ?? null,
                        'confidence_reason' => $row['confidence_reason'] ?? null,
                        'recommendation_label' => $row['recommendation_label'] ?? null,
                        'recommendation_reason' => $row['recommendation_reason'] ?? null,
                        'score' => $row['score'] ?? null,
                        'reasons' => $row['reasons'] ?? [],
                        'duplicate_signals' => $row['duplicate_signals'] ?? [],
                        'selected_by_default' => $row['default_checked'] ?? false,
                    ],
                    'normalized_domain' => $row['normalized_domain'] ?? null,
                    'name_norm' => $this->truncate($row['display_name'] ?? $row['link_text'] ?? $row['category_label'] ?? null, 255),
                    'pref' => $meta['pref'] ?? null,
                    'city' => $meta['city'] ?? null,
                    'fetched_at' => now(),
                ]);

                $saved++;
            }
        });

        session()->forget("directory_source_lab_previews.{$token}");

        return redirect()
            ->route('directory-sources.lab')
            ->with('status', "名簿元候補を {$saved} 件 source_records に保存した。営業先companyは自動作成していない。");
    }

    private function splitLines(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $cleaned = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $cleaned[] = $line;
        }

        return array_values(array_unique($cleaned));
    }

    private function buildSummary(array $rows, array $entryResults, array $excluded): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = (string) ($row['category_key'] ?? 'other');
            $groups[$key] = ($groups[$key] ?? 0) + 1;
        }

        return [
            'entry_total' => count($entryResults),
            'entry_ok' => collect($entryResults)->filter(fn ($entry) => !empty($entry['ok']))->count(),
            'total' => count($rows),
            'default_checked' => collect($rows)->filter(fn ($row) => !empty($row['default_checked']))->count(),
            'high' => collect($rows)->filter(fn ($row) => ($row['confidence_label'] ?? '') === '高')->count(),
            'excluded' => count($excluded),
            'groups' => $groups,
        ];
    }

    private function truncate(?string $value, int $limit): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Str::limit($value, $limit, '');
    }

    private function cleanupOldPreviews(): void
    {
        $previews = session()->get('directory_source_lab_previews', []);
        if (!is_array($previews) || empty($previews)) {
            return;
        }

        $threshold = now()->subHours(3)->timestamp;
        foreach ($previews as $token => $preview) {
            $createdAt = (int) data_get($preview, 'meta.created_at', 0);
            if ($createdAt > 0 && $createdAt < $threshold) {
                unset($previews[$token]);
            }
        }

        session()->put('directory_source_lab_previews', $previews);
    }
}
