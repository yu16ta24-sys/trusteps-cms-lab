<?php

namespace App\Http\Controllers;

use App\Models\SourceRecord;
use App\Services\Discovery\ShokokaiWebSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShokokaiWebSearchController extends Controller
{
    public function __construct(
        private readonly ShokokaiWebSearchService $searchService
    ) {
    }

    public function show(): View
    {
        $this->cleanupOldPreviews();

        return view('directory-sources.shokokai-web-search', [
            'preview' => null,
            'prefectures' => config('discovery.shokokai_web_search_prefectures', []),
        ]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $prefectures = config('discovery.shokokai_web_search_prefectures', []);
        $prefCodes = implode(',', array_keys($prefectures));

        $validator = Validator::make($request->all(), [
            'pref_code' => ['required', 'string', 'in:' . $prefCodes],
            'kensu' => ['required', 'integer', 'in:10,20,50'],
            'max_pages' => ['required', 'integer', 'min:1', 'max:' . (int) config('discovery.shokokai_web_search_page_hard_limit', 20)],
            'shokokai' => ['nullable', 'string', 'max:100'],
        ], [
            'pref_code.required' => '都道府県を選んでから検索して。',
            'pref_code.in' => '対応している都道府県コードではない。',
            'kensu.in' => '表示件数は10・20・50のいずれかにして。',
            'max_pages.max' => '最大ページ数が大きすぎる。件数を分けて実行して。',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('directory-sources.shokokai-web-search')
                ->withInput()
                ->withErrors($validator);
        }

        $validated = $validator->validated();
        $prefCode = (string) $validated['pref_code'];
        $kensu = (int) $validated['kensu'];
        $maxPages = (int) $validated['max_pages'];
        $keyword = trim((string) ($validated['shokokai'] ?? ''));

        $result = $this->searchService->search($prefCode, $kensu, $maxPages, $keyword);
        $token = (string) Str::uuid();

        $preview = [
            'token' => $token,
            'meta' => [
                'pref_code' => $prefCode,
                'pref_label' => $prefectures[$prefCode] ?? $prefCode,
                'kensu' => $kensu,
                'max_pages' => $maxPages,
                'shokokai' => $keyword,
                'created_at' => now()->timestamp,
            ],
            'page_results' => $result['page_results'] ?? [],
            'rows' => $result['rows'] ?? [],
            'excluded' => $result['excluded'] ?? [],
            'summary' => $result['summary'] ?? [],
        ];

        session()->put("shokokai_web_search_previews.{$token}", $preview);

        return view('directory-sources.shokokai-web-search', [
            'preview' => $preview,
            'prefectures' => $prefectures,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $token = (string) $request->input('token', '');
        $selectedRows = array_map('intval', (array) $request->input('selected_rows', []));
        $preview = session()->get("shokokai_web_search_previews.{$token}");

        if (!$preview || !is_array($preview)) {
            return redirect()
                ->route('directory-sources.shokokai-web-search')
                ->withErrors(['token' => 'プレビュー情報が見つからない。もう一度検索して。']);
        }

        if (empty($selectedRows)) {
            return redirect()
                ->route('directory-sources.shokokai-web-search')
                ->withErrors(['selected_rows' => '保存する商工会HPを1件以上選択して。']);
        }

        $rows = collect($preview['rows'] ?? [])
            ->filter(fn ($row) => in_array((int) ($row['row_id'] ?? -1), $selectedRows, true))
            ->filter(fn ($row) => !empty($row['storable']))
            ->values();

        if ($rows->isEmpty()) {
            return redirect()
                ->route('directory-sources.shokokai-web-search')
                ->withErrors(['selected_rows' => '保存できる候補が選択されていない。URL要確認の候補は保存対象外。']);
        }

        $saved = 0;

        DB::transaction(function () use ($rows, $preview, &$saved): void {
            foreach ($rows as $row) {
                SourceRecord::create([
                    'source_type' => 'directory_source_candidate',
                    'source_url' => $row['url'] ?? null,
                    'raw_json' => [
                        'collector_version' => '0.18.8',
                        'collector_type' => 'shokokai_web_search',
                        'source_name' => '全国商工会WEBサーチ',
                        'source_url' => config('discovery.shokokai_web_search_endpoint'),
                        'search_meta' => $preview['meta'] ?? [],
                        'pref_code' => $row['pref_code'] ?? null,
                        'pref_label' => $row['pref_label'] ?? null,
                        'organization_name' => $row['organization_name'] ?? null,
                        'organization_type' => $row['organization_type'] ?? null,
                        'url' => $row['url'] ?? null,
                        'postal_code' => $row['postal_code'] ?? null,
                        'address' => $row['address'] ?? null,
                        'tel' => $row['tel'] ?? null,
                        'fax' => $row['fax'] ?? null,
                        'shokokai_code' => $row['shokokai_code'] ?? null,
                        'raw_index' => $row['raw_index'] ?? null,
                        'category_key' => $row['category_key'] ?? null,
                        'category_label' => $row['category_label'] ?? null,
                        'confidence_label' => $row['confidence_label'] ?? null,
                        'confidence_reason' => $row['confidence_reason'] ?? null,
                        'recommendation_label' => $row['recommendation_label'] ?? null,
                        'recommendation_reason' => $row['recommendation_reason'] ?? null,
                        'duplicate_signals' => $row['duplicate_signals'] ?? [],
                        'selected_by_default' => $row['default_checked'] ?? false,
                    ],
                    'normalized_domain' => $row['normalized_domain'] ?? null,
                    'name_norm' => $this->truncate($row['organization_name'] ?? null, 255),
                    'pref' => $row['pref_label'] ?? null,
                    'city' => null,
                    'fetched_at' => now(),
                ]);

                $saved++;
            }
        });

        session()->forget("shokokai_web_search_previews.{$token}");

        return redirect()
            ->route('directory-sources.shokokai-web-search')
            ->with('status', "商工会HPを {$saved} 件、名簿元候補としてsource_recordsに保存した。営業先companyは自動作成していない。");
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
        $previews = session()->get('shokokai_web_search_previews', []);
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

        session()->put('shokokai_web_search_previews', $previews);
    }
}
