@extends('layouts.app', ['title' => '業界スコア | TRUSTEPS CMS Lab'])

@section('content')
<main class="content isc">
<style>
.isc { display:grid; gap:20px; }
.isc-topbar { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px; }
.isc-kicker { font-size:11px; font-weight:900; color:var(--muted); letter-spacing:.1em; text-transform:uppercase; margin-bottom:6px; }
.isc-title { margin:0; font-size:28px; font-weight:950; letter-spacing:-.03em; color:var(--text); }
.isc-sub { margin:5px 0 0; font-size:13px; color:var(--muted); }
.isc-parent-card { background:#fff; border:1px solid var(--line); border-radius:20px; overflow:hidden; }
.isc-parent-head { padding:16px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; cursor:pointer; user-select:none; }
.isc-parent-head:hover { background:#f8fafc; }
.isc-parent-name { font-size:16px; font-weight:950; letter-spacing:-.02em; color:var(--text); }
.isc-parent-meta { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.isc-parent-actions { display:flex; gap:8px; align-items:center; }
.isc-table-wrap { overflow-x:auto; border-top:1px solid var(--line); }
.isc-table { width:100%; border-collapse:collapse; font-size:13px; }
.isc-table th { padding:10px 12px; background:#f9fafb; color:#344054; font-size:11px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid var(--line); white-space:nowrap; text-align:center; }
.isc-table th:first-child { text-align:left; }
.isc-table td { padding:8px 12px; border-bottom:1px solid #f0f4f8; vertical-align:middle; }
.isc-table tbody tr:last-child td { border-bottom:none; }
.isc-table tbody tr:hover td { background:#fafbfc; }
.isc-industry-name { font-weight:900; font-size:13px; color:var(--text); }
.isc-industry-slug { font-size:11px; color:var(--muted); font-family:monospace; margin-top:2px; }
.isc-score-cell { text-align:center; }
.isc-score-badge { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:999px; font-weight:900; font-size:13px; }
.isc-score-badge.high { background:#dcfce7; color:#166534; }
.isc-score-badge.mid  { background:#fef3c7; color:#92400e; }
.isc-score-badge.low  { background:#fee2e2; color:#991b1b; }
.isc-score-badge.none { background:#f2f4f7; color:#d0d5dd; font-size:11px; }
.isc-score-input { width:52px; height:32px; border:1px solid #d9e2ee; border-radius:8px; text-align:center; font-size:14px; font-weight:900; background:#fff; color:var(--text); }
.isc-score-input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(31,94,255,.12); }
.isc-score-input.changed { border-color:#f97316; background:#fff7ed; }
.isc-edit-mode-off .isc-score-input { display:none; }
.isc-edit-mode-off .isc-score-badge { display:inline-flex; }
.isc-edit-mode-on .isc-score-input { display:inline-block; }
.isc-edit-mode-on .isc-score-badge { display:none; }
.isc-axis-header { display:grid; gap:2px; }
.isc-axis-label { font-size:11px; font-weight:900; letter-spacing:.04em; }
.isc-axis-key { font-size:10px; color:var(--muted); font-family:monospace; font-weight:400; text-transform:none; letter-spacing:0; }
.isc-save-bar { padding:12px 20px; background:#fff7ed; border-top:1px solid #fed7aa; display:none; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.isc-save-bar.visible { display:flex; }
.isc-legend { display:flex; gap:12px; align-items:center; flex-wrap:wrap; font-size:11px; color:var(--muted); }
.isc-legend-item { display:flex; align-items:center; gap:4px; }
.isc-score-cell--amber { background:#fef3c7 !important; }
.isc-obs { font-size:10px; color:#6b7280; margin-top:3px; white-space:nowrap; line-height:1.3; }
.isc-obs--ref { color:#d1d5db; }
</style>

<div class="isc-topbar">
    <div>
        <div class="isc-kicker">Industry Scores · Layer 1</div>
        <h1 class="isc-title">業界スコア</h1>
        <p class="isc-sub">業種ごとのCMS事業適性・参入余白を仮説値として管理する。大分類ごとに編集モードで一括入力できる。</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <div class="mini-card" style="text-align:center;padding:12px 18px;">
            <div style="font-size:10px;font-weight:900;letter-spacing:.08em;color:var(--muted);">AXES</div>
            <div style="font-size:24px;font-weight:950;margin-top:2px;">{{ $axes->count() }}</div>
            <div style="font-size:11px;color:var(--muted);">有効軸</div>
        </div>
        <a class="button light small" href="{{ route('industries.scores.export') }}">CSVエクスポート</a>
        <a class="button light small" href="{{ route('industries.scores.import') }}">CSVインポート</a>
        <a class="button light small" href="{{ route('dashboard') }}">Dashboard</a>
    </div>
</div>

@if(session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

{{-- 凡例 --}}
<div class="isc-legend">
    <span style="font-weight:900;font-size:11px;color:var(--muted);">スコア:</span>
    <span class="isc-legend-item"><span class="isc-score-badge high" style="width:24px;height:24px;font-size:11px;">4+</span> 高い</span>
    <span class="isc-legend-item"><span class="isc-score-badge mid"  style="width:24px;height:24px;font-size:11px;">2-3</span> 中</span>
    <span class="isc-legend-item"><span class="isc-score-badge low"  style="width:24px;height:24px;font-size:11px;">0-1</span> 低い</span>
    <span class="isc-legend-item"><span class="isc-score-badge none" style="width:24px;height:24px;font-size:10px;">—</span> 未設定</span>
    <span style="margin-left:8px;font-size:11px;color:var(--muted);">※ portal_dependency / wp_penetration は高いほどネガティブ</span>
</div>

{{-- 大分類ループ --}}
@foreach($parents as $parent)
    @php
        $parentChildren = $children->get($parent->id, collect());
        $settledCount = $parentChildren->filter(fn($c) => ($summaries[$c->slug]['filled_count'] ?? 0) > 0)->count();
        $totalCount = $parentChildren->count();
    @endphp

    <div class="isc-parent-card" id="parent-{{ $parent->id }}">

        {{-- 大分類ヘッダー --}}
        <div class="isc-parent-head" onclick="toggleSection({{ $parent->id }})">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span class="isc-parent-name">{{ $parent->name }}</span>
                <span class="badge gray" style="font-size:11px;">{{ $totalCount }}種</span>
                @if($settledCount > 0)
                    <span class="badge {{ $settledCount >= $totalCount ? 'green' : 'amber' }}" style="font-size:11px;">{{ $settledCount }}/{{ $totalCount }} 設定済</span>
                @else
                    <span style="font-size:11px;color:var(--muted);">未設定</span>
                @endif
            </div>
            <div class="isc-parent-actions" onclick="event.stopPropagation()">
                <button type="button" class="button light small" onclick="toggleEdit({{ $parent->id }})">
                    <span id="edit-label-{{ $parent->id }}">編集モード</span>
                </button>
                <span id="toggle-icon-{{ $parent->id }}" style="font-size:12px;color:var(--muted);min-width:16px;text-align:center;">▼</span>
            </div>
        </div>

        {{-- テーブル本体 --}}
        <div id="section-{{ $parent->id }}" class="isc-edit-mode-off">
            @if($parentChildren->isEmpty())
                <div style="padding:16px 20px;color:var(--muted);font-size:13px;">中分類なし</div>
            @else
                <form method="POST" action="{{ route('industries.scores.bulk-update', $parent->slug) }}" id="form-{{ $parent->id }}">
                    @csrf

                    <div class="isc-table-wrap">
                        <table class="isc-table">
                            <thead>
                                <tr>
                                    <th style="min-width:160px;text-align:left;">中分類</th>
                                    @foreach($axes as $axis)
                                        <th style="min-width:72px;">
                                            <div class="isc-axis-header">
                                                <span class="isc-axis-label">{{ $axis->label }}</span>
                                                <span class="isc-axis-key">{{ $axis->key }}</span>
                                            </div>
                                        </th>
                                    @endforeach
                                    <th style="min-width:60px;">入力済</th>
                                    <th style="min-width:80px;">更新日</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($parentChildren as $child)
                                    @php
                                        $summary = $summaries[$child->slug] ?? ['filled_count' => 0, 'updated_at' => null, 'categories' => [], 'scores' => collect()];
                                        $industryScores = $summary['scores'];
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="isc-industry-name">{{ $child->name }}</div>
                                            <div class="isc-industry-slug">{{ $child->slug }}</div>
                                        </td>
                                        @foreach($axes as $axis)
                                            @php
                                                $score = $industryScores->get($axis->key);
                                                $val = $score?->value;
                                                $badgeClass = $val === null ? 'none' : ($val >= 4 ? 'high' : ($val >= 2 ? 'mid' : 'low'));
                                                $obs = $observationStats->get($child->id . '_' . $axis->key);
                                                $obsAvg = $obs ? (float)$obs->avg_value : null;
                                                $obsCount = $obs ? (int)$obs->obs_count : 0;
                                                $deviation = ($val !== null && $obsAvg !== null) ? abs($val - $obsAvg) : null;
                                                $isAmber = $deviation !== null && $deviation >= 1;
                                            @endphp
                                            <td class="isc-score-cell{{ $isAmber ? ' isc-score-cell--amber' : '' }}">
                                                <span class="isc-score-badge {{ $badgeClass }}">
                                                    {{ $val !== null ? $val : '—' }}
                                                </span>
                                                @if($obs)
                                                    @if($obsCount < 5)
                                                        <div class="isc-obs isc-obs--ref">参考 n={{ $obsCount }}</div>
                                                    @else
                                                        <div class="isc-obs">実績: {{ number_format($obsAvg, 1) }} (n={{ $obsCount }})</div>
                                                    @endif
                                                @endif
                                                <input
                                                    type="number"
                                                    class="isc-score-input"
                                                    name="scores[{{ $child->slug }}][{{ $axis->key }}][value]"
                                                    value="{{ $val !== null ? $val : '' }}"
                                                    min="0"
                                                    max="5"
                                                    placeholder="—"
                                                    data-original="{{ $val !== null ? $val : '' }}"
                                                    oninput="markChanged(this, {{ $parent->id }})"
                                                >
                                            </td>
                                        @endforeach
                                        <td style="text-align:center;">
                                            @if($summary['filled_count'] > 0)
                                                <span class="badge {{ $summary['filled_count'] >= $axes->count() ? 'green' : 'amber' }}" style="font-size:11px;">
                                                    {{ $summary['filled_count'] }}/{{ $axes->count() }}
                                                </span>
                                            @else
                                                <span style="color:#d0d5dd;font-size:12px;">—</span>
                                            @endif
                                        </td>
                                        <td style="font-size:11px;color:var(--muted);white-space:nowrap;">
                                            {{ $summary['updated_at'] ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- 保存バー --}}
                    <div class="isc-save-bar" id="save-bar-{{ $parent->id }}">
                        <div style="font-size:13px;color:#92400e;font-weight:900;">
                            ⚠ 未保存の変更があります
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button type="button" class="button light small" onclick="cancelEdit({{ $parent->id }})">キャンセル</button>
                            <button type="submit" class="button small">{{ $parent->name }} を保存</button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endforeach

</main>

@push('scripts')
<script>
const sectionStates = {};

function toggleSection(parentId) {
    const section = document.getElementById('section-' + parentId);
    const icon = document.getElementById('toggle-icon-' + parentId);
    if (!section) return;
    const isHidden = section.style.display === 'none';
    section.style.display = isHidden ? '' : 'none';
    if (icon) icon.textContent = isHidden ? '▼' : '▶';
}

function toggleEdit(parentId) {
    const section = document.getElementById('section-' + parentId);
    const label = document.getElementById('edit-label-' + parentId);
    if (!section) return;

    const isEditing = section.classList.contains('isc-edit-mode-on');

    if (isEditing) {
        cancelEdit(parentId);
    } else {
        section.classList.remove('isc-edit-mode-off');
        section.classList.add('isc-edit-mode-on');
        if (label) label.textContent = '閲覧モード';
        // セクションが閉じていたら開く
        if (section.style.display === 'none') {
            section.style.display = '';
            const icon = document.getElementById('toggle-icon-' + parentId);
            if (icon) icon.textContent = '▼';
        }
    }
}

function cancelEdit(parentId) {
    const section = document.getElementById('section-' + parentId);
    const label = document.getElementById('edit-label-' + parentId);
    const saveBar = document.getElementById('save-bar-' + parentId);
    if (!section) return;

    // 値をoriginalに戻す
    section.querySelectorAll('.isc-score-input').forEach(input => {
        input.value = input.dataset.original;
        input.classList.remove('changed');
    });

    section.classList.remove('isc-edit-mode-on');
    section.classList.add('isc-edit-mode-off');
    if (label) label.textContent = '編集モード';
    if (saveBar) saveBar.classList.remove('visible');
}

function markChanged(input, parentId) {
    const saveBar = document.getElementById('save-bar-' + parentId);
    const val = input.value.trim();
    const orig = input.dataset.original;

    // 0-5の範囲チェック
    if (val !== '' && (parseInt(val) < 0 || parseInt(val) > 5)) {
        input.value = orig;
        return;
    }

    if (val !== orig) {
        input.classList.add('changed');
    } else {
        input.classList.remove('changed');
    }

    // 変更があれば保存バーを表示
    const section = document.getElementById('section-' + parentId);
    const hasChanged = section && section.querySelectorAll('.isc-score-input.changed').length > 0;
    if (saveBar) saveBar.classList.toggle('visible', hasChanged);
}

// 保存後にoriginalを更新（ページリロードで自動反映されるので不要だが念のため）
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.isc-score-input').forEach(input => {
        input.dataset.original = input.value;
    });
});
</script>
@endpush
@endsection
