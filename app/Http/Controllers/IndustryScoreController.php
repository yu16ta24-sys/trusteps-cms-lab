<?php

namespace App\Http\Controllers;

use App\Models\Industry;
use App\Models\IndustryScore;
use App\Models\IndustryScoreAxis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IndustryScoreController extends Controller
{
    private array $categoryLabels = [
        'opportunity' => '機会系',
        'white_space' => '参入余白系',
        'execution'   => '営業・実行系',
        'risk'        => 'リスク系',
    ];

    public function index(): View
    {
        $parents = Industry::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $children = Industry::query()
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('parent_id');

        $allSlugs = Industry::query()
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->pluck('slug')
            ->filter()
            ->values();

        $axes   = $this->activeAxes();
        $scores = IndustryScore::query()
            ->whereIn('industry_key', $allSlugs)
            ->get()
            ->groupBy('industry_key');

        $categoryKeys = $axes->pluck('category')->unique()->values();
        $summaries    = [];

        foreach ($allSlugs as $slug) {
            $industryScores  = $scores->get($slug, collect())->keyBy('axis_key');
            $latestUpdatedAt = $industryScores->pluck('updated_at')->filter()->sortDesc()->first();

            $summaries[$slug] = [
                'filled_count' => $industryScores->filter(fn($s) => $s->value !== null)->count(),
                'updated_at'   => $latestUpdatedAt ? $latestUpdatedAt->format('Y-m-d') : null,
                'categories'   => [],
                'scores'       => $industryScores,
            ];

            foreach ($categoryKeys as $category) {
                $categoryAxes = $axes->where('category', $category);
                $values = $categoryAxes
                    ->map(fn($axis) => optional($industryScores->get($axis->key))->value)
                    ->filter(fn($v) => $v !== null)
                    ->values();

                $summaries[$slug]['categories'][$category] = $values->isEmpty()
                    ? null
                    : round((float) $values->avg(), 1);
            }
        }

        $observationStats = DB::table('industry_score_observations')
            ->select('industry_id', 'axis_key', DB::raw('ROUND(AVG(value), 1) as avg_value'), DB::raw('COUNT(*) as obs_count'))
            ->groupBy('industry_id', 'axis_key')
            ->get()
            ->keyBy(fn($row) => $row->industry_id . '_' . $row->axis_key);

        return view('industries.scores.index', [
            'parents'          => $parents,
            'children'         => $children,
            'axes'             => $axes,
            'categoryLabels'   => $this->categoryLabels,
            'categoryKeys'     => $categoryKeys,
            'summaries'        => $summaries,
            'observationStats' => $observationStats,
        ]);
    }

    public function edit(string $industry): View
    {
        $industryModel = Industry::query()->where('slug', $industry)->firstOrFail();
        $axes   = $this->activeAxes();
        $scores = IndustryScore::query()
            ->where('industry_key', $industryModel->slug)
            ->get()
            ->keyBy('axis_key');

        return view('industries.scores.edit', [
            'industry'       => $industryModel,
            'axesByCategory' => $axes->groupBy('category'),
            'scores'         => $scores,
            'categoryLabels' => $this->categoryLabels,
            'scoreTypes'     => [
                'hypothesis' => '仮説',
                'observed'   => '実測',
                'mixed'      => '混合',
            ],
            'confidences' => [
                ''       => '未設定',
                'low'    => '低',
                'medium' => '中',
                'high'   => '高',
            ],
        ]);
    }

    public function update(Request $request, string $industry): RedirectResponse
    {
        $industryModel = Industry::query()->where('slug', $industry)->firstOrFail();
        $axes = $this->activeAxes();

        $validated = $request->validate([
            'scores'                => ['nullable', 'array'],
            'scores.*.value'        => ['nullable', 'integer', 'min:0', 'max:5'],
            'scores.*.confidence'   => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'scores.*.score_type'   => ['nullable', Rule::in(['hypothesis', 'observed', 'mixed'])],
            'scores.*.note'         => ['nullable', 'string', 'max:5000'],
        ]);

        $inputScores = collect($validated['scores'] ?? []);

        DB::transaction(function () use ($industryModel, $axes, $inputScores) {
            foreach ($axes as $axis) {
                $payload    = (array) $inputScores->get($axis->key, []);
                $value      = array_key_exists('value', $payload) && $payload['value'] !== '' ? (int) $payload['value'] : null;
                $confidence = trim((string) ($payload['confidence'] ?? '')) ?: null;
                $scoreType  = trim((string) ($payload['score_type'] ?? '')) ?: 'hypothesis';
                $note       = trim((string) ($payload['note'] ?? ''));
                $hasContent = $value !== null || $confidence !== null || $note !== '' || $scoreType !== 'hypothesis';

                if (!$hasContent) {
                    IndustryScore::query()
                        ->where('industry_key', $industryModel->slug)
                        ->where('axis_key', $axis->key)
                        ->delete();
                    continue;
                }

                IndustryScore::query()->updateOrCreate(
                    ['industry_key' => $industryModel->slug, 'axis_key' => $axis->key],
                    [
                        'value'      => $value,
                        'confidence' => $confidence,
                        'score_type' => $scoreType,
                        'note'       => $note !== '' ? $note : null,
                        'updated_by' => auth()->id(),
                    ]
                );
            }
        });

        return redirect()
            ->route('industries.scores.edit', $industryModel->slug)
            ->with('status', '業界スコアを保存しました。');
    }

    /**
     * 大分類単位の一括保存（インライン編集UIから呼ばれる）
     * POST /industries/scores/bulk-update/{parent}
     */
    public function bulkUpdateByParent(Request $request, string $parent): RedirectResponse
    {
        $parentModel = Industry::query()
            ->whereNull('parent_id')
            ->where('slug', $parent)
            ->firstOrFail();

        $childSlugs = Industry::query()
            ->where('parent_id', $parentModel->id)
            ->where('is_active', true)
            ->pluck('slug')
            ->filter()
            ->values();

        $axes = $this->activeAxes();

        $request->validate([
            'scores'                      => ['nullable', 'array'],
            'scores.*'                    => ['nullable', 'array'],
            'scores.*.*.value'            => ['nullable', 'integer', 'min:0', 'max:5'],
        ]);

        $inputScores = collect($request->input('scores', []));

        DB::transaction(function () use ($childSlugs, $axes, $inputScores) {
            foreach ($childSlugs as $slug) {
                $slugScores = (array) $inputScores->get($slug, []);

                foreach ($axes as $axis) {
                    $payload = (array) ($slugScores[$axis->key] ?? []);
                    $rawValue = $payload['value'] ?? null;

                    if ($rawValue === null || $rawValue === '') {
                        // 空欄は既存レコードを削除
                        IndustryScore::query()
                            ->where('industry_key', $slug)
                            ->where('axis_key', $axis->key)
                            ->delete();
                        continue;
                    }

                    $value = (int) $rawValue;
                    if ($value < 0 || $value > 5) {
                        continue;
                    }

                    IndustryScore::query()->updateOrCreate(
                        ['industry_key' => $slug, 'axis_key' => $axis->key],
                        [
                            'value'      => $value,
                            'confidence' => 'low',
                            'score_type' => 'hypothesis',
                            'note'       => null,
                            'updated_by' => auth()->id(),
                        ]
                    );
                }
            }
        });

        return redirect()
            ->route('industries.scores.index')
            ->with('status', "{$parentModel->name} の業界スコアを保存しました。");
    }

    public function export(): StreamedResponse
    {
        $axes     = $this->activeAxes();
        $children = Industry::query()
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $scores = IndustryScore::all()
            ->groupBy('industry_key')
            ->map(fn($g) => $g->keyBy('axis_key'));

        return response()->streamDownload(function () use ($axes, $children, $scores) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            // ヘッダー行: industry_key, industry_name, axis1, axis2, ...
            $headerRow = ['industry_key', 'industry_name'];
            foreach ($axes as $axis) {
                $headerRow[] = $axis->key;
            }
            fputcsv($out, $headerRow);

            // データ行: 業種1行ずつ、各軸の value（未設定は空白）
            foreach ($children as $industry) {
                $row            = [$industry->slug, $industry->name];
                $industryScores = $scores->get($industry->slug, collect());
                foreach ($axes as $axis) {
                    $score = $industryScores->get($axis->key);
                    $row[] = $score !== null ? (string) $score->value : '';
                }
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'industry_scores_' . date('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importForm(): View
    {
        $axisKeys = $this->activeAxes()->pluck('key')->implode(', ');
        return view('industries.scores.import', compact('axisKeys'));
    }

    public function importPreview(Request $request): View|RedirectResponse
    {
        $request->validate(['csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:4096']]);

        $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
        $bom    = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header         = array_map('trim', fgetcsv($handle) ?: []);
        $industryKeyIdx = array_search('industry_key', $header, true);
        if ($industryKeyIdx === false) {
            fclose($handle);
            return back()->withErrors(['csv_file' => '必須列「industry_key」がCSVに含まれていません。']);
        }

        // ヘッダーから有効な軸列を特定（industry_key / industry_name 以外で axis_key に一致するもの）
        $validAxes  = IndustryScoreAxis::where('is_active', true)->pluck('label', 'key');
        $skipCols   = ['industry_key', 'industry_name'];
        $axisCols   = []; // [col_index => axis_key]
        foreach ($header as $idx => $col) {
            if (in_array($col, $skipCols, true)) {
                continue;
            }
            if ($validAxes->has($col)) {
                $axisCols[$idx] = $col;
            }
        }

        if (empty($axisCols)) {
            fclose($handle);
            return back()->withErrors(['csv_file' => '有効な軸列が1つも見つかりませんでした。ヘッダーに axis_key を列名として含めてください。']);
        }

        $validSlugs = Industry::whereNotNull('parent_id')->where('is_active', true)->pluck('name', 'slug');
        $existing   = IndustryScore::all()->keyBy(fn($s) => $s->industry_key . '::' . $s->axis_key);

        $rows = $errors = [];
        $line = 1;

        while (($raw = fgetcsv($handle)) !== false) {
            $line++;
            $industryKey = trim($raw[$industryKeyIdx] ?? '');
            if ($industryKey === '') {
                continue;
            }
            if (!isset($validSlugs[$industryKey])) {
                $errors[] = "行{$line}: industry_key「{$industryKey}」が存在しません。";
                continue;
            }

            foreach ($axisCols as $colIdx => $axisKey) {
                $rawValue = trim($raw[$colIdx] ?? '');
                if ($rawValue === '') {
                    continue; // 空白はスキップ（既存を削除しない）
                }
                if (!ctype_digit($rawValue) || (int) $rawValue < 0 || (int) $rawValue > 5) {
                    $errors[] = "行{$line} / {$axisKey}: 値「{$rawValue}」は 0〜5 の整数が必要です。";
                    continue;
                }

                $key     = $industryKey . '::' . $axisKey;
                $current = $existing->get($key);

                $rows[] = [
                    'industry_key'  => $industryKey,
                    'industry_name' => $validSlugs[$industryKey],
                    'axis_key'      => $axisKey,
                    'axis_label'    => $validAxes[$axisKey],
                    'value'         => (int) $rawValue,
                    'current_value' => $current?->value,
                    'is_new'        => $current === null,
                    'is_changed'    => $current !== null && $current->value !== (int) $rawValue,
                ];
            }
        }
        fclose($handle);

        if (!empty($errors)) {
            return back()->withErrors(['csv_file' => implode("\n", $errors)]);
        }
        if (empty($rows)) {
            return back()->withErrors(['csv_file' => '有効なデータ行がありませんでした。']);
        }

        session(['industry_score_import_rows' => $rows]);

        $axisKeys = $this->activeAxes()->pluck('key')->implode(', ');
        return view('industries.scores.import', compact('rows', 'axisKeys'));
    }

    public function importStore(): RedirectResponse
    {
        $rows = session('industry_score_import_rows', []);

        if (empty($rows)) {
            return redirect()->route('industries.scores.import')
                ->withErrors(['csv_file' => 'セッションが切れました。再度CSVをアップロードしてください。']);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                // 既存レコードは value のみ更新（confidence / score_type / note を保持）
                $score = IndustryScore::firstOrNew([
                    'industry_key' => $row['industry_key'],
                    'axis_key'     => $row['axis_key'],
                ]);
                $score->value      = $row['value'];
                $score->updated_by = auth()->id();
                if (!$score->exists) {
                    $score->confidence = null;
                    $score->score_type = 'hypothesis';
                    $score->note       = null;
                }
                $score->save();
            }
        });

        session()->forget('industry_score_import_rows');

        return redirect()->route('industries.scores.index')
            ->with('status', count($rows) . '件の業界スコアをインポートしました。');
    }

    private function activeAxes(): Collection
    {
        return IndustryScoreAxis::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
