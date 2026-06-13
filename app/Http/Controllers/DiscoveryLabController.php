<?php

namespace App\Http\Controllers;

use App\Models\SourceRecord;
use App\Services\Discovery\DirectoryLinkExtractor;
use App\Services\Discovery\UrlCandidateClassifier;
use App\Support\NameNormalizer;
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
        $validator = Validator::make($request->all(), [
            'urls' => ['required', 'string', 'max:200000'],
            'default_source_type' => ['nullable', 'string', 'max:80'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'pref' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'raw_industry' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:2000'],
        ], [
            'urls.required' => 'URLリストを1件以上入力してからプレビューして。',
            'urls.max' => 'URLリストが長すぎる。件数を分けて投入して。',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('discovery.lab')
                ->withInput()
                ->withErrors($validator);
        }

        $validated = $validator->validated();

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

            $row = $this->enrichCandidateQuality($row);
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
        $validator = Validator::make($request->all(), [
            'directory_url' => ['required', 'string', 'max:2000'],
            'default_source_type' => ['nullable', 'string', 'max:80'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'pref' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'raw_industry' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:2000'],
            'follow_detail_pages' => ['nullable', 'boolean'],
            'detail_page_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'directory_url.required' => '名簿ページURLを入力してからプレビューして。',
            'directory_url.max' => '名簿ページURLが長すぎる。URLを確認して。',
            'detail_page_limit.integer' => '詳細ページ取得上限は数字で入力して。',
            'detail_page_limit.min' => '詳細ページ取得上限は1以上で入力して。',
            'detail_page_limit.max' => '詳細ページ取得上限は最大50件まで。',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('discovery.lab')
                ->withInput()
                ->withErrors($validator);
        }

        $validated = $validator->validated();

        $directoryUrl = trim($validated['directory_url']);
        $extraction = $this->directoryExtractor->extract($directoryUrl, [
            'follow_detail_pages' => $request->boolean('follow_detail_pages'),
            'detail_page_limit' => (int) ($validated['detail_page_limit'] ?? config('discovery.directory_detail_page_limit', 20)),
        ]);

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
            'detail_stats' => $extraction['detail_stats'] ?? null,
            'filter_stats' => $extraction['filter_stats'] ?? [],
            'follow_detail_pages' => $request->boolean('follow_detail_pages'),
            'excluded_links' => [],
            'excluded_links_total' => 0,
        ];

        $classified = [];
        $sourceDomain = $this->hostFromUrl($meta['source_page_url'] ?? null);
        $directoryFilterStats = [
            'source_domain_hidden' => 0,
            'existing_domain_hidden' => 0,
            'preview_duplicate_domain_hidden' => 0,
        ];
        $seenPrimaryDomains = [];
        $excludedLinks = $extraction['excluded_links'] ?? [];

        foreach (($extraction['links'] ?? []) as $index => $link) {
            $row = $this->classifier->classify($link['url'] ?? '');
            $row = $this->applyDirectoryCandidateMeta($row, $link);
            $row['line_number'] = $index + 1;
            $row['link_text'] = $link['text'] ?? '';
            $row['link_context'] = $link['context'] ?? '';
            $row['source_page_url'] = $meta['source_page_url'];
            $row['candidate_type'] = $link['candidate_type'] ?? null;
            $row['detail_page_url'] = $link['detail_page_url'] ?? null;
            $row['detail_page_title'] = $link['detail_page_title'] ?? null;
            $row['detail_parent_text'] = $link['detail_parent_text'] ?? null;
            $row['detail_parent_context'] = $link['detail_parent_context'] ?? null;
            $row['discovery_method'] = !empty($link['detail_page_url']) ? 'directory_detail_extract' : 'directory_link_extract';
            $row['duplicate_signals'] = $this->duplicateSignals($row['normalized_url'], $row['normalized_domain']);
            $row['fanout_count'] = $row['normalized_domain'] ? SourceRecord::query()->where('normalized_domain', $row['normalized_domain'])->count() : 0;

            if ($this->shouldHideDirectoryCandidate($row, $sourceDomain, $seenPrimaryDomains, $directoryFilterStats, $excludedLinks)) {
                continue;
            }

            $row['row_id'] = count($classified);
            $row['high_fanout_warning'] = $this->hasHighFanoutWarning($row['normalized_domain'], $classified, $row['fanout_count']);

            if ($row['high_fanout_warning']) {
                $row['warnings'][] = '同一ドメイン候補が多い。ポータル/共有ドメイン/誤統合に注意。';
                $row['warnings'] = array_values(array_unique($row['warnings']));
            }

            $row = $this->enrichCandidateQuality($row);
            $row['default_checked'] = $this->shouldDefaultCheck($row);
            $classified[] = $row;
        }

        $meta['filter_stats'] = array_merge($meta['filter_stats'] ?? [], $directoryFilterStats);
        $meta['excluded_links_total'] = count($excludedLinks);
        $meta['excluded_links'] = array_slice($excludedLinks, 0, 80);

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
        }, 'discovery_lab_candidates_v0.18.5.csv', [
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

    private function shouldHideDirectoryCandidate(array $row, ?string $sourceDomain, array &$seenPrimaryDomains, array &$filterStats, array &$excludedLinks): bool
    {
        $domain = $row['normalized_domain'] ?? null;

        if ($domain && $sourceDomain && $domain === $sourceDomain) {
            $filterStats['source_domain_hidden']++;
            $this->addExcludedLink($excludedLinks, $row, '名簿元ドメインと同じ内部リンクのため候補から除外');
            return true;
        }

        if (!$this->isPrimaryDirectoryCandidate($row)) {
            return false;
        }

        if ($domain && (int) ($row['fanout_count'] ?? 0) > 0) {
            $filterStats['existing_domain_hidden']++;
            $this->addExcludedLink($excludedLinks, $row, '既存source_recordsに同一ドメインがあるため候補から除外');
            return true;
        }

        if ($domain) {
            $dedupeKey = $this->directoryCandidateDomainKey($domain, $row['normalized_url'] ?? null, $row['classification'] ?? null);
            if (isset($seenPrimaryDomains[$dedupeKey])) {
                $filterStats['preview_duplicate_domain_hidden']++;
                $this->addExcludedLink($excludedLinks, $row, '同一プレビュー内で同じ候補ドメインが先に出ているため除外');
                return true;
            }
            $seenPrimaryDomains[$dedupeKey] = true;
        }

        return false;
    }

    private function isPrimaryDirectoryCandidate(array $row): bool
    {
        return in_array($row['classification'] ?? null, [
            'official_site_candidate',
            'builder_site_candidate',
        ], true);
    }

    private function directoryCandidateDomainKey(string $domain, ?string $url, ?string $classification): string
    {
        if (in_array($classification, ['sns_candidate', 'portal_candidate', 'map_candidate', 'ec_candidate'], true) && $url) {
            $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
            $firstSegment = explode('/', $path)[0] ?? '';
            return $firstSegment !== '' ? $domain . '/' . mb_strtolower($firstSegment) : $domain;
        }

        return $domain;
    }

    private function hostFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        return preg_replace('/^www\./', '', strtolower($host));
    }

    private function shouldDefaultCheck(array $row): bool
    {
        if (empty($row['is_valid_url'])) {
            return false;
        }

        if (!empty($row['duplicate_signals']) || !empty($row['high_fanout_warning'])) {
            return false;
        }

        return in_array($row['classification'], [
            'official_site_candidate',
            'builder_site_candidate',
        ], true);
    }


    private function addExcludedLink(array &$excludedLinks, array $row, string $reason): void
    {
        if (count($excludedLinks) >= 200) {
            return;
        }

        $excludedLinks[] = [
            'url' => $row['normalized_url'] ?? $row['raw_url'] ?? null,
            'domain' => $row['normalized_domain'] ?? null,
            'text' => $row['link_text'] ?? null,
            'classification' => $row['classification'] ?? 'unknown',
            'label' => $row['classification_label'] ?? '不明',
            'reason' => $reason,
            'detail_page_url' => $row['detail_page_url'] ?? null,
        ];
    }

    private function enrichCandidateQuality(array $row): array
    {
        [$groupKey, $groupLabel] = $this->candidateGroup($row['classification'] ?? 'unknown');
        $row['candidate_group'] = $groupKey;
        $row['candidate_group_label'] = $groupLabel;

        $classification = $row['classification'] ?? 'unknown';
        $fromDetail = !empty($row['detail_page_url']);
        $hasDuplicate = !empty($row['duplicate_signals']);
        $hasFanout = !empty($row['high_fanout_warning']);

        if (empty($row['is_valid_url'])) {
            $row['confidence_rank'] = 'invalid';
            $row['confidence_label'] = '無効';
            $row['confidence_reason'] = 'URLとして正規化できないため保存不可';
            $row['recommendation_label'] = '保存不可';
            $row['recommendation_reason'] = '無効URL';
            return $row;
        }

        if ($classification === 'official_site_candidate' && $fromDetail) {
            $row['confidence_rank'] = 'high';
            $row['confidence_label'] = '高';
            $row['confidence_reason'] = '名簿の事業者詳細ページ内で発見した外部リンク';
            $row['recommendation_label'] = '保存推奨';
            $row['recommendation_reason'] = '公式HP候補かつ詳細ページ由来';
        } elseif ($classification === 'official_site_candidate') {
            $row['confidence_rank'] = 'medium';
            $row['confidence_label'] = '中';
            $row['confidence_reason'] = '名簿一覧または手動投入に含まれていた外部の独自ドメイン候補';
            $row['recommendation_label'] = '保存推奨';
            $row['recommendation_reason'] = '公式HP候補';
        } elseif ($classification === 'builder_site_candidate') {
            $row['confidence_rank'] = 'medium';
            $row['confidence_label'] = '中';
            $row['confidence_reason'] = $fromDetail ? '詳細ページ内で発見したビルダー系サイト' : 'Wix/Jimdo等のビルダー系サイト';
            $row['recommendation_label'] = '保存推奨';
            $row['recommendation_reason'] = '公式HP代替として使われている可能性がある';
        } elseif (in_array($classification, ['sns_candidate', 'ec_candidate'], true)) {
            $row['confidence_rank'] = 'low';
            $row['confidence_label'] = '低';
            $row['confidence_reason'] = 'SNS/ECは公式HPそのものではないため補助情報扱い';
            $row['recommendation_label'] = '必要時のみ';
            $row['recommendation_reason'] = '公式HPがない場合の補助候補';
        } elseif (in_array($classification, ['portal_candidate', 'map_candidate', 'pdf_candidate'], true)) {
            $row['confidence_rank'] = 'low';
            $row['confidence_label'] = '低';
            $row['confidence_reason'] = 'ポータル/Map/PDFは営業候補URLとしては低優先';
            $row['recommendation_label'] = '原則保存しない';
            $row['recommendation_reason'] = '公式HP候補ではない';
        } else {
            $row['confidence_rank'] = 'low';
            $row['confidence_label'] = '低';
            $row['confidence_reason'] = '分類不能のため手動確認が必要';
            $row['recommendation_label'] = '手動確認';
            $row['recommendation_reason'] = '自動分類の根拠が弱い';
        }

        if ($hasDuplicate || $hasFanout) {
            $row['confidence_rank'] = 'review';
            $row['confidence_label'] = '要確認';
            $row['recommendation_label'] = '保存前確認';
            $row['recommendation_reason'] = $hasDuplicate ? '既存データとの重複シグナルあり' : '同一ドメイン候補が多い';
        }

        return $row;
    }

    private function candidateGroup(string $classification): array
    {
        return match ($classification) {
            'official_site_candidate' => ['official', '公式候補'],
            'builder_site_candidate' => ['builder', 'ビルダー系'],
            'sns_candidate' => ['sns', 'SNS'],
            'ec_candidate' => ['ec', 'EC・モール'],
            'portal_candidate' => ['portal', 'ポータル'],
            'map_candidate' => ['map', 'Map'],
            'pdf_candidate' => ['pdf', 'PDF'],
            default => ['other', 'その他・不明'],
        };
    }

    private function applyDirectoryCandidateMeta(array $row, array $link): array
    {
        $candidateType = $link['candidate_type'] ?? null;

        if ($candidateType === 'directory_detail_candidate') {
            $row['classification'] = 'directory_detail_candidate';
            $row['classification_label'] = '詳細候補';
            $row['badge_color'] = 'gray';
            $row['confidence'] = 0.35;
            $row['warnings'][] = '商工会・団体サイト内の事業者詳細ページ候補。公式HPではないため通常は保存しない。詳細ページ掘り下げONで公式HP候補を探す対象。';
            $row['warnings'] = array_values(array_unique($row['warnings']));
            $row['default_checked'] = false;
        }

        if ($candidateType === 'detail_external_link') {
            $row['warnings'][] = '事業者詳細ページ内で発見した外部リンク。名簿一覧から直接ではなく詳細ページ由来。';
            $row['warnings'] = array_values(array_unique($row['warnings']));
            if (($row['classification'] ?? null) === 'official_site_candidate') {
                $row['confidence'] = min(0.85, (float) ($row['confidence'] ?? 0.70) + 0.10);
            }
        }

        return $row;
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
            'discovery_method' => $row['discovery_method'] ?? ($isDirectory ? 'directory_link_extract' : 'manual_url_list'),
            'no_http_fetch' => !$isDirectory,
            'http_fetch_scope' => $isDirectory ? (!empty($meta['follow_detail_pages']) ? 'directory_page_and_one_level_detail_pages' : 'directory_page_only') : null,
            'link_text' => $row['link_text'] ?? null,
            'link_context' => $row['link_context'] ?? null,
            'raw_url' => $row['raw_url'] ?? null,
            'normalized_url' => $row['normalized_url'] ?? null,
            'normalized_domain' => $row['normalized_domain'] ?? null,
            'url_classification' => $row['classification'] ?? 'unknown',
            'classification_label' => $row['classification_label'] ?? '不明',
            'candidate_group' => $row['candidate_group'] ?? 'other',
            'candidate_group_label' => $row['candidate_group_label'] ?? 'その他・不明',
            'confidence' => $row['confidence'] ?? 0,
            'confidence_rank' => $row['confidence_rank'] ?? 'low',
            'confidence_label' => $row['confidence_label'] ?? '低',
            'confidence_reason' => $row['confidence_reason'] ?? null,
            'recommendation_label' => $row['recommendation_label'] ?? null,
            'recommendation_reason' => $row['recommendation_reason'] ?? null,
            'selected_by_default' => $row['default_checked'] ?? false,
            'warnings' => $row['warnings'] ?? [],
            'duplicate_signals' => $row['duplicate_signals'] ?? [],
            'high_fanout_warning' => $row['high_fanout_warning'] ?? false,
            'fanout_count_at_preview' => $row['fanout_count'] ?? 0,
            'pref' => $pref ?: null,
            'city' => $city ?: null,
            'raw_industry' => $rawIndustry ?: null,
            'memo' => $meta['memo'] ?? null,
            'fetch_warnings' => $meta['fetch_warnings'] ?? [],
            'detail_stats' => $meta['detail_stats'] ?? null,
            'filter_stats' => $meta['filter_stats'] ?? [],
            'candidate_type' => $row['candidate_type'] ?? null,
            'detail_page_url' => $row['detail_page_url'] ?? null,
            'detail_page_title' => $row['detail_page_title'] ?? null,
            'detail_parent_text' => $row['detail_parent_text'] ?? null,
            'detail_parent_context' => $row['detail_parent_context'] ?? null,
            'created_from' => 'discovery_lab v0.18.5 ' . $inputType,
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
            'discovery_lab_v0.18.5',
            'classification=' . ($row['classification'] ?? 'unknown'),
            'confidence=' . ($row['confidence'] ?? 0),
            'confidence_label=' . ($row['confidence_label'] ?? '-'),
            'reason=' . ($row['confidence_reason'] ?? '-'),
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
        return NameNormalizer::normalize($name) ?: null;
    }
}
