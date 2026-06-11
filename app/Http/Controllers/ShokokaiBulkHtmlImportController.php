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
        $manualUrls = (array) $request->input('manual_urls', []);
        $preview = session()->get("shokokai_bulk_html_previews.{$token}");

        if (!$preview || !is_array($preview)) {
            return redirect()
                ->route('directory-sources.shokokai-bulk-html')
                ->withErrors(['token' => 'プレビュー情報が見つからない。もう一度HTMLを貼ってプレビューして。']);
        }

        $manualUrlCount = collect($manualUrls)
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->count();

        if (empty($selectedRows) && $manualUrlCount === 0) {
            return redirect()
                ->route('directory-sources.shokokai-bulk-html')
                ->withErrors(['selected_rows' => '保存する商工会HPを1件以上選択するか、URLなし行に公式HP URLを手入力して。']);
        }

        $invalidManualUrls = [];
        $rows = collect($preview['rows'] ?? [])
            ->map(function ($row) use ($selectedRows, $manualUrls, &$invalidManualUrls) {
                $rowId = (int) ($row['row_id'] ?? -1);
                $manualUrl = trim((string) ($manualUrls[$rowId] ?? $manualUrls[(string) $rowId] ?? ''));
                $isSelected = in_array($rowId, $selectedRows, true);

                if ($manualUrl !== '') {
                    $manualUrlInfo = $this->normalizeManualUrl($manualUrl);
                    if (empty($manualUrlInfo['valid'])) {
                        $invalidManualUrls[] = sprintf('%s：%s', (string) ($row['organization_name'] ?? '名称未取得'), $manualUrl);
                        $row['manual_url_invalid'] = true;
                        return $row;
                    }

                    $row['original_status_key'] = $row['status_key'] ?? null;
                    $row['original_status_label'] = $row['status_label'] ?? null;
                    $row['manual_url'] = $manualUrlInfo['url'];
                    $row['url'] = $manualUrlInfo['url'];
                    $row['raw_url'] = $manualUrl;
                    $row['normalized_domain'] = $manualUrlInfo['domain'];
                    $row['url_path_key'] = $manualUrlInfo['path_key'];
                    $row['url_identity_key'] = $manualUrlInfo['identity_key'];
                    $row['status_key'] = 'valid_url';
                    $row['status_label'] = '有効URL（手入力）';
                    $row['storable'] = true;
                    $row['default_checked'] = true;
                    $row['manual_url_provided'] = true;
                    $row['confidence_label'] = '手動確認';
                    $row['confidence_reason'] = 'URLなし/URL要確認の行に対して、Google確認後に手入力された公式HP候補。';
                    $row['recommendation_label'] = '手入力URLを保存';
                    $row['recommendation_reason'] = '人間が確認して入力したURLのため、名簿元候補として保存する。';
                    $row['duplicate_signals'] = array_values(array_filter($row['duplicate_signals'] ?? [], fn ($signal) => !in_array($signal, ['URLなし', 'URL要確認'], true)));
                    $row['duplicate_signals'][] = '手入力URL';

                    return $row;
                }

                if (!$isSelected) {
                    $row['storable'] = false;
                }

                return $row;
            })
            ->filter(fn ($row) => !empty($row['storable']) && empty($row['manual_url_invalid']))
            ->values();

        if (!empty($invalidManualUrls)) {
            return redirect()
                ->route('directory-sources.shokokai-bulk-html')
                ->withErrors(['manual_urls' => '手入力URLの形式が不正。http(s)のURL、またはドメイン形式で入力して。対象：' . implode(' / ', array_slice($invalidManualUrls, 0, 5))]);
        }

        if ($rows->isEmpty()) {
            return redirect()
                ->route('directory-sources.shokokai-bulk-html')
                ->withErrors(['selected_rows' => '保存可能な候補がない。URLなし・URL要確認の行は、Google確認後に公式HP URLを入力すると保存できる。']);
        }

        $saved = 0;

        DB::transaction(function () use ($rows, $preview, &$saved): void {
            foreach ($rows as $row) {
                SourceRecord::create([
                    'source_type' => 'directory_source_candidate',
                    'source_url' => $row['url'] ?? null,
                    'raw_json' => [
                        'collector_version' => '0.18.9.4',
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
                        'manual_url_provided' => $row['manual_url_provided'] ?? false,
                        'manual_url' => $row['manual_url'] ?? null,
                        'original_status_key' => $row['original_status_key'] ?? null,
                        'original_status_label' => $row['original_status_label'] ?? null,
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

    /**
     * @return array{url:?string,domain:?string,path_key:?string,identity_key:?string,valid:bool}
     */
    private function normalizeManualUrl(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['url' => null, 'domain' => null, 'path_key' => null, 'identity_key' => null, 'valid' => false];
        }

        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./i', '', $host) ?: '';

        if ($host === '' || str_starts_with($host, '.') || str_ends_with($host, '.') || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['url' => $url, 'domain' => $host ?: null, 'path_key' => null, 'identity_key' => null, 'valid' => false];
        }

        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        $path = '/' . ltrim($path, '/');
        $pathKey = rtrim($path, '/') ?: '/';
        $query = (string) ($parts['query'] ?? '');
        $identityKey = $host . $pathKey . ($query !== '' ? '?' . $query : '');

        return [
            'url' => $url,
            'domain' => $host,
            'path_key' => $pathKey,
            'identity_key' => $identityKey,
            'valid' => true,
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
