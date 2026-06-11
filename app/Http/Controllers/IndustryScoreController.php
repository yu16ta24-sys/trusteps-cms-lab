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

        return view('industries.scores.index', [
            'parents'        => $parents,
            'children'       => $children,
            'axes'           => $axes,
            'categoryLabels' => $this->categoryLabels,
            'categoryKeys'   => $categoryKeys,
            'summaries'      => $summaries,
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

    private function activeAxes(): Collection
    {
        return IndustryScoreAxis::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
