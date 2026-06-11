<?php

namespace App\Http\Controllers;

use App\Models\SourceRecord;
use App\Services\Discovery\ShokokaiBulkHtmlImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShokokaiBulkHtmlImportController extends Controller
{
    public function __construct(
        private readonly ShokokaiBulkHtmlImportService $importService
    ) {
    }

    public function show(): View
    {
        $this->cleanupOldPreviews();

        return view('directory-sources.shokokai-bulk-html', [
            'preview' => null,
            'htmlInput' => '',
        ]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $html = (string) $request->input('html', '');
        $clientRowsJson = (string) $request->input('client_rows_json', '');

        if (trim($html) === '' && trim($clientRowsJson) === '') {
            return redirect()
                ->route('directory-sources.shokokai-bulk-html')
                ->withErrors(['html' => '全国商工会WEBサーチの全件表示HTMLを貼り付けてからプレビューして。']);
        }

        $preview = null;
        $usedClientRows = false;

        if (trim($clientRowsJson) !== '') {
            $clientRows = json_decode($clientRowsJson, true);
            if (!is_array($clientRows)) {
                return redirect()
                    ->route('directory-sources.shokokai-bulk-html')
                    ->withErrors(['html' => 'ブラウザ側のHTML前処理結果を読み込めなかった。ページを再読み込みしてもう一度試して。']);
            }

            if (count($clientRows) > 5000) {
                return redirect()
                    ->route('directory-sources.shokokai-bulk-html')
                    ->withErrors(['html' => '一度に処理できる件数は5000件まで。HTMLを分割して取り込んで。']);
            }

            $preview = $this->importService->previewClientRows($clientRows);
            $usedClientRows = true;
        } else {
            $validator = Validator::make(['html' => $html], [
                'html' => ['required', 'string', 'max:800000'],
            ], [
                'html.required' => '全国商工会WEBサーチの全件表示HTMLを貼り付けてからプレビューして。',
                'html.max' => '貼り付けHTMLが大きすぎるため、ブラウザ側の前処理を使ってプレビューして。',
            ]);

            if ($validator->fails()) {
                return redirect()
                    ->route('directory-sources.shokokai-bulk-html')
                    ->withErrors($validator);
            }

            $preview = $this->importService->preview($html);
        }

        if ((int) data_get($preview, 'summary.total', 0) === 0) {
            return redirect()
                ->route('directory-sources.shokokai-bulk-html')
                ->withErrors(['html' => '商工会データを抽出できなかった。<li>...</li> が並んでいる検索結果HTMLを貼って。']);
        }

        $token = Str::random(40);
        session()->put("shokokai_bulk_html_previews.{$token}", $preview);

        $preview['token'] = $token;
        $preview['used_client_rows'] = $usedClientRows;

        return view('directory-sources.shokokai-bulk-html', [
            'preview' => $preview,
            'htmlInput' => '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $token = (string) $request->input('token', '');
        $selectedRows = array_map('intval', (array) $request->input('selected_rows', []));
        $preview = session()->get("shokokai_bulk_html_previews.{$token}");

        if (!$preview || !is_array($preview)) {
            return redirect()
                ->route('directory-sources.shokokai-bulk-html')
                ->withErrors(['token' => 'プレビュー情報が見つからない。もう一度HTMLを貼ってプレビューして。']);
        }

        if (empty($selectedRows)) {
            return redirect()
                ->route('directory-sources.shokokai-bulk-html')
                ->withErrors(['selected_rows' => '保存する商工会HPを1件以上選択して。']);
        }

        $rows = collect($preview['rows'] ?? [])
            ->filter(fn ($row) => in_array((int) ($row['row_id'] ?? -1), $selectedRows, true))
            ->filter(fn ($row) => !empty($row['storable']))
            ->values();

        if ($rows->isEmpty()) {
            return redirect()
                ->route('directory-sources.shokokai-bulk-html')
                ->withErrors(['selected_rows' => '保存可能な候補が選択されていない。URLなし・URL要確認の候補は保存対象外。']);
        }

        $saved = 0;

        DB::transaction(function () use ($rows, $preview, &$saved): void {
            foreach ($rows as $row) {
                SourceRecord::create([
                    'source_type' => 'directory_source_candidate',
                    'source_url' => $row['url'] ?? null,
                    'raw_json' => [
                        'collector_version' => '0.18.9.3',
                        'collector_type' => 'shokokai_web_search_bulk_html',
                        'origin' => 'shokokai_web_search_bulk_html',
                        'source_name' => '全国商工会WEBサーチ 全件HTML',
                        'search_meta' => $preview['meta'] ?? [],
                        'pref_code' => $row['pref_code'] ?? null,
                        'pref_label' => $row['pref_label'] ?? null,
                        'organization_name' => $row['organization_name'] ?? null,
                        'organization_type' => $row['organization_type'] ?? null,
                        'organization_type_label' => $row['organization_type_label'] ?? null,
                        'url' => $row['url'] ?? null,
                        'raw_url' => $row['raw_url'] ?? null,
                        'url_path_key' => $row['url_path_key'] ?? null,
                        'url_identity_key' => $row['url_identity_key'] ?? null,
                        'postal_code' => $row['postal_code'] ?? null,
                        'address' => $row['address'] ?? null,
                        'tel' => $row['tel'] ?? null,
                        'fax' => $row['fax'] ?? null,
                        'shokokai_code' => $row['shokokai_code'] ?? null,
                        'raw_index' => $row['raw_index'] ?? null,
                        'status_key' => $row['status_key'] ?? null,
                        'status_label' => $row['status_label'] ?? null,
                        'category_label' => $row['category_label'] ?? null,
                        'confidence_label' => $row['confidence_label'] ?? null,
                        'confidence_reason' => $row['confidence_reason'] ?? null,
                        'recommendation_label' => $row['recommendation_label'] ?? null,
                        'recommendation_reason' => $row['recommendation_reason'] ?? null,
                        'duplicate_signals' => $row['duplicate_signals'] ?? [],
                        'selected_by_default' => $row['default_checked'] ?? false,
                        'search_query' => $row['search_query'] ?? null,
                        'google_search_url' => $row['google_search_url'] ?? null,
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

        session()->forget("shokokai_bulk_html_previews.{$token}");

        return redirect()
            ->route('directory-sources.shokokai-bulk-html')
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
        $previews = session()->get('shokokai_bulk_html_previews', []);
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

        session()->put('shokokai_bulk_html_previews', $previews);
    }
}
