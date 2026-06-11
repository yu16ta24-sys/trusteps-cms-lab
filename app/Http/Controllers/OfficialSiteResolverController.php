<?php

namespace App\Http\Controllers;

use App\Models\SourceRecord;
use App\Services\Resolver\OfficialSiteResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OfficialSiteResolverController extends Controller
{
    public function __construct(
        private readonly OfficialSiteResolver $resolver
    ) {
    }

    public function show(): View
    {
        $this->cleanupOldPreviews();

        return view('resolver.official-sites', [
            'preview' => null,
        ]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'urls' => ['required', 'string', 'max:100000'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'pref' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'raw_industry' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:2000'],
        ], [
            'urls.required' => '公式HP候補URLを1件以上入力してからプレビューして。',
            'urls.max' => 'URLリストが長すぎる。件数を分けて投入して。',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('resolver.official-sites.index')
                ->withInput()
                ->withErrors($validator);
        }

        $validated = $validator->validated();
        $limit = (int) config('discovery.official_site_resolver_url_limit', 30);
        $urls = $this->splitLines((string) $validated['urls']);

        if (count($urls) > $limit) {
            return redirect()
                ->route('resolver.official-sites.index')
                ->withInput()
                ->withErrors(['urls' => "一度に解析できるURLは最大 {$limit} 件。件数を分けて投入して。"]);
        }

        $meta = [
            'source_name' => trim($validated['source_name'] ?? '') ?: 'Official Site Resolver MVP',
            'pref' => trim($validated['pref'] ?? ''),
            'city' => trim($validated['city'] ?? ''),
            'raw_industry' => trim($validated['raw_industry'] ?? ''),
            'memo' => trim($validated['memo'] ?? ''),
            'created_at' => now()->timestamp,
        ];

        $rows = $this->resolver->analyzeMany($urls);
        $token = (string) Str::uuid();
        $preview = [
            'token' => $token,
            'meta' => $meta,
            'rows' => $rows,
            'summary' => $this->buildSummary($rows),
        ];

        session()->put("official_site_resolver_previews.{$token}", $preview);

        return view('resolver.official-sites', [
            'preview' => $preview,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $token = (string) $request->input('token', '');
        $selectedRows = array_map('intval', (array) $request->input('selected_rows', []));
        $preview = session()->get("official_site_resolver_previews.{$token}");

        if (!$preview || !is_array($preview)) {
            return redirect()
                ->route('resolver.official-sites.index')
                ->withErrors(['token' => 'プレビュー情報が見つからない。もう一度プレビューして。']);
        }

        if (empty($selectedRows)) {
            return redirect()
                ->route('resolver.official-sites.index')
                ->withErrors(['selected_rows' => '保存するURLを1件以上選択して。']);
        }

        $meta = $preview['meta'] ?? [];
        $rows = collect($preview['rows'] ?? [])
            ->filter(fn ($row) => in_array((int) ($row['row_id'] ?? -1), $selectedRows, true))
            ->values();

        if ($rows->isEmpty()) {
            return redirect()
                ->route('resolver.official-sites.index')
                ->withErrors(['selected_rows' => '選択されたURLがプレビュー内に見つからない。']);
        }

        $saved = 0;

        DB::transaction(function () use ($rows, $meta, &$saved): void {
            foreach ($rows as $row) {
                SourceRecord::create([
                    'source_type' => 'official_site_resolver_mvp',
                    'source_url' => $row['final_url'] ?? $row['normalized_url'] ?? $row['input_url'] ?? null,
                    'raw_json' => [
                        'resolver_version' => '0.18.6',
                        'resolver_type' => 'official_site_resolver_mvp',
                        'source_name' => $meta['source_name'] ?? null,
                        'raw_industry' => $meta['raw_industry'] ?? null,
                        'memo' => $meta['memo'] ?? null,
                        'input_url' => $row['input_url'] ?? null,
                        'requested_url' => $row['requested_url'] ?? null,
                        'final_url' => $row['final_url'] ?? null,
                        'http_status' => $row['http_status'] ?? null,
                        'content_type' => $row['content_type'] ?? null,
                        'title' => $row['title'] ?? null,
                        'meta_description' => $row['meta_description'] ?? null,
                        'meta_generator' => $row['meta_generator'] ?? null,
                        'canonical_url' => $row['canonical_url'] ?? null,
                        'og_site_name' => $row['og_site_name'] ?? null,
                        'ssl_enabled' => $row['ssl_enabled'] ?? null,
                        'wordpress_detected' => $row['wordpress_detected'] ?? null,
                        'wordpress_signals' => $row['wordpress_signals'] ?? [],
                        'cms_guess' => $row['cms_guess'] ?? null,
                        'builder_guess' => $row['builder_guess'] ?? null,
                        'has_contact_form' => $row['has_contact_form'] ?? null,
                        'has_public_email' => $row['has_public_email'] ?? null,
                        'has_phone' => $row['has_phone'] ?? null,
                        'emails' => $row['emails'] ?? [],
                        'phones' => $row['phones'] ?? [],
                        'confidence_label' => $row['confidence_label'] ?? null,
                        'confidence_reason' => $row['confidence_reason'] ?? null,
                        'recommendation_label' => $row['recommendation_label'] ?? null,
                        'recommendation_reason' => $row['recommendation_reason'] ?? null,
                        'warnings' => $row['warnings'] ?? [],
                        'duplicate_signals' => $row['duplicate_signals'] ?? [],
                    ],
                    'normalized_domain' => $row['normalized_domain'] ?? null,
                    'name_norm' => $this->truncate($row['title'] ?? $row['og_site_name'] ?? null, 255),
                    'pref' => $meta['pref'] ?? null,
                    'city' => $meta['city'] ?? null,
                    'fetched_at' => !empty($row['ok']) ? now() : null,
                ]);

                $saved++;
            }
        });

        session()->forget("official_site_resolver_previews.{$token}");

        return redirect()
            ->route('resolver.official-sites.index')
            ->with('status', "公式HP取得結果を {$saved} 件 source_records に保存した。companyは自動作成していない。");
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

    private function buildSummary(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'ok' => 0,
            'wordpress' => 0,
            'ssl' => 0,
            'contact' => 0,
            'default_checked' => 0,
            'high' => 0,
            'needs_review' => 0,
        ];

        foreach ($rows as $row) {
            if (!empty($row['ok'])) {
                $summary['ok']++;
            }
            if (!empty($row['wordpress_detected'])) {
                $summary['wordpress']++;
            }
            if (!empty($row['ssl_enabled'])) {
                $summary['ssl']++;
            }
            if (!empty($row['has_contact_form']) || !empty($row['has_public_email']) || !empty($row['has_phone'])) {
                $summary['contact']++;
            }
            if (!empty($row['default_checked'])) {
                $summary['default_checked']++;
            }
            if (($row['confidence_label'] ?? '') === '高') {
                $summary['high']++;
            }
            if (in_array(($row['confidence_label'] ?? ''), ['要確認', '無効'], true)) {
                $summary['needs_review']++;
            }
        }

        return $summary;
    }

    private function cleanupOldPreviews(): void
    {
        $previews = session()->get('official_site_resolver_previews', []);
        if (!is_array($previews)) {
            session()->forget('official_site_resolver_previews');
            return;
        }

        $threshold = now()->subHours(3)->timestamp;
        foreach ($previews as $token => $preview) {
            if (($preview['meta']['created_at'] ?? 0) < $threshold) {
                unset($previews[$token]);
            }
        }

        session()->put('official_site_resolver_previews', $previews);
    }

    private function truncate(mixed $value, int $limit): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return Str::limit($value, $limit, '');
    }
}
