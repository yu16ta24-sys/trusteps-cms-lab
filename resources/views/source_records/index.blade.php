@extends('layouts.app', ['title' => 'Source Records | TRUSTEPS CMS Lab'])

@section('content')
<main class="content sr">
<style>
.sr { display:grid; gap:20px; }
.sr-topbar { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px; }
.sr-kicker { font-size:11px; font-weight:900; color:var(--muted); letter-spacing:.1em; text-transform:uppercase; margin-bottom:6px; }
.sr-title { margin:0; font-size:28px; font-weight:950; letter-spacing:-.03em; color:var(--text); }
.sr-sub { margin:5px 0 0; font-size:13px; color:var(--muted); }
.sr-btn-row { display:flex; gap:8px; flex-wrap:wrap; }
.sr-sec-label { font-size:10px; font-weight:900; color:var(--muted); letter-spacing:.1em; text-transform:uppercase; margin-bottom:12px; }
.sr-filter-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
.sr-info-card { border-radius:16px; padding:14px 18px; }
.sr-info-card.blue { background:#eff6ff; border:1px solid #bfdbfe; }
.sr-info-card.red { background:#fff1f2; border:1px solid #fecdd3; }
.sr-info-card.amber { background:#fff7ed; border:1px solid #fed7aa; }
.sr-info-card.gray { background:#f8fafc; border:1px solid var(--line); }
.sr-info-row { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; }
.sr-info-title { font-size:13px; font-weight:900; color:var(--text); margin-bottom:4px; }
.sr-info-sub { font-size:12px; color:var(--muted); line-height:1.6; margin:0; }
.sr-chip-row { display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
.sr-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.sr-count { font-size:13px; color:var(--muted); }
.sr-table-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px; }
.compact-pagination { margin-top:18px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; color:#475467; font-size:13px; }
.compact-pagination .pagination-links { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.compact-pagination .page-link, .compact-pagination .page-ellipsis { min-width:34px; height:34px; padding:0 10px; display:inline-flex; align-items:center; justify-content:center; border-radius:999px; border:1px solid #d9e2ee; background:rgba(255,255,255,.82); color:#344054; font-weight:850; text-decoration:none; line-height:1; }
.compact-pagination .page-link:hover { background:#f8fafc; color:#0f172a; }
.compact-pagination .page-link.active { background:#0f172a; color:#fff; border-color:#0f172a; }
.compact-pagination .page-link.disabled { opacity:.45; cursor:not-allowed; }
.compact-pagination .page-ellipsis { border-color:transparent; background:transparent; min-width:20px; padding:0 2px; }
.compact-pagination .pagination-count { color:#667085; font-weight:700; }
.sr-bulk-bar {
    position:fixed; bottom:0; left:0; right:0; z-index:50;
    background:#0f172a; color:#fff;
    padding:10px 24px;
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    box-shadow:0 -3px 10px rgba(0,0,0,.22);
    font-size:13px;
}
.sr-bulk-bar .btn-exclude { background:#dc2626; border:1px solid #b91c1c; color:#fff; }
.sr-bulk-bar .btn-delete  { background:#7f1d1d; border:1px solid #991b1b; color:#fff; }
.sr-bulk-bar .btn-deselect { background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2); color:#fff; }
</style>

{{-- ヘッダー --}}
<div class="sr-topbar">
    <div>
        <div class="sr-kicker">Phase1 / Intake</div>
        <h1 class="sr-title">source_records</h1>
        <p class="sr-sub">外部から取った生データを整理する入口。company化前の営業先候補を確認する。</p>
    </div>
    <div class="sr-btn-row">
        <a class="button light small" href="{{ route('source-records.import') }}">CSV取り込み</a>
        <a class="button small" href="{{ route('source-records.create') }}">手動登録</a>
    </div>
</div>

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="error">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

{{-- フィルター --}}
<section class="card">
    <div class="sr-sec-label">Filter</div>
    <form method="GET" action="{{ route('source-records.index') }}">
        <div class="sr-filter-grid">
            <div class="field" style="margin-bottom:0;">
                <label for="q">語句検索</label>
                <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="会社名・URL・法人番号など">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label for="source_type">source_type</label>
                <select id="source_type" name="source_type">
                    <option value="">すべて</option>
                    @foreach ($sourceTypes as $type)
                        <option value="{{ $type }}" @selected(request('source_type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-bottom:0;">
                <label for="pref-select-sr">都道府県</label>
                <select id="pref-select-sr" name="pref">
                    <option value="">すべて</option>
                    @foreach ($prefectures as $pref)
                        <option value="{{ $pref->name }}" @selected(request('pref') === $pref->name)>{{ $pref->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-bottom:0;">
                <label for="city-select-sr">市区町村</label>
                <select id="city-select-sr" name="city">
                    <option value="">すべて</option>
                </select>
            </div>
            <div class="field" style="margin-bottom:0;">
                <label for="raw_industry">業種</label>
                <select id="raw_industry" name="raw_industry">
                    <option value="">すべて</option>
                    @foreach ($rawIndustryOptions as $industry)
                        <option value="{{ $industry }}" @selected(request('raw_industry') === $industry)>{{ $industry }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-bottom:0;">
                <label for="link_status">状態</label>
                <select id="link_status" name="link_status">
                    <option value="unlinked" @selected(request('link_status', 'unlinked') === 'unlinked')>未リンク（未company化）</option>
                    <option value="linked" @selected(request('link_status') === 'linked')>company化済み</option>
                    <option value="all" @selected(request('link_status') === 'all')>すべて表示</option>
                </select>
            </div>
            <div class="field" style="margin-bottom:0; align-self:end;">
                <button class="button" type="submit">絞り込み</button>
                <a class="button light" href="{{ route('source-records.index') }}">リセット</a>
            </div>
        </div>
    </form>
</section>

@php
    $sortKey = $sort ?? request('sort', 'id');
    $sortDirection = $direction ?? request('direction', 'desc');
    $sortUrl = function (string $key) use ($sortKey, $sortDirection) {
        $nextDirection = ($sortKey === $key && $sortDirection === 'asc') ? 'desc' : 'asc';
        return route('source-records.index', array_merge(request()->except(['page']), [
            'sort' => $key,
            'direction' => $nextDirection,
        ]));
    };
    $sortMark = function (string $key) use ($sortKey, $sortDirection) {
        if ($sortKey !== $key) return '';
        return $sortDirection === 'asc' ? ' ↑' : ' ↓';
    };
    $currentPageUnlinked = $sourceRecords->getCollection()->filter(function ($record) {
        return !$record->sourceLink;
    });
    $currentPageLinkedCount = $sourceRecords->getCollection()->filter(fn ($record) => (bool) $record->sourceLink)->count();
    $firstUnlinkedId = optional($currentPageUnlinked->first())->id;
    $activeFilterItems = collect([
        ['key' => 'q', 'label' => '語句', 'value' => request('q')],
        ['key' => 'source_type', 'label' => 'source_type', 'value' => request('source_type')],
        ['key' => 'pref', 'label' => '都道府県', 'value' => request('pref')],
        ['key' => 'city', 'label' => '市区町村', 'value' => request('city')],
        ['key' => 'raw_industry', 'label' => '業種', 'value' => request('raw_industry')],
        ['key' => 'link_status', 'label' => '状態', 'value' => request('link_status') === 'linked' ? 'company化済み' : (request('link_status') === 'all' ? 'すべて表示' : null)],
    ])->filter(fn ($item) => $item['value'] !== null && $item['value'] !== '')->values();
    $filterRemoveUrl = function (string $key) {
        return route('source-records.index', request()->except(['page', $key]));
    };
    $unlinkedOnlyUrl = route('source-records.index', array_merge(request()->except(['page']), ['link_status' => 'unlinked']));
    $clearStatusUrl = route('source-records.index', request()->except(['page', 'link_status']));
    $clearLocationUrl = route('source-records.index', request()->except(['page', 'pref', 'city']));
@endphp

{{-- 作業セッション --}}
<div class="sr-info-card amber">
    <div class="sr-info-row">
        <div>
            <div class="sr-info-title">作業セッション</div>
            <p class="sr-info-sub">
                一覧：{{ number_format($sourceRecords->total()) }}件 / このページ：{{ number_format($sourceRecords->count()) }}件。
                未リンク：{{ number_format($currentPageUnlinked->count()) }}件 / company化済み：{{ number_format($currentPageLinkedCount) }}件。
            </p>
            @if ($activeFilterItems->isNotEmpty())
                <div class="sr-chip-row">
                    @foreach ($activeFilterItems as $item)
                        <a class="badge gray" style="text-decoration:none;" href="{{ $filterRemoveUrl($item['key']) }}">
                            {{ $item['label'] }}：{{ $item['value'] }} ×
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="sr-actions">
            @if ($firstUnlinkedId)
                <a class="button small" href="{{ route('source-records.show', $firstUnlinkedId) }}">先頭未リンク #{{ $firstUnlinkedId }}</a>
            @else
                <span class="button light small" style="opacity:.55;cursor:not-allowed;">このページは未リンクなし</span>
            @endif
        </div>
    </div>
</div>

{{-- フィルター操作 + 処理キュー --}}
<div class="sr-info-card gray">
    <div class="sr-info-row">
        <div>
            <div class="sr-info-title">処理キュー</div>
            <p class="sr-info-sub">company化対象未リンク {{ number_format($unlinkedQueueCount ?? 0) }} 件</p>
        </div>
        <div class="sr-actions">
            <a class="button light small" href="{{ $unlinkedOnlyUrl }}">未リンクだけ表示</a>
            @if (request('link_status'))
                <a class="button light small" href="{{ $clearStatusUrl }}">状態だけ解除</a>
            @endif
            @if (request('pref') || request('city'))
                <a class="button light small" href="{{ $clearLocationUrl }}">地域だけ解除</a>
            @endif
            <a class="button light small" href="{{ route('source-records.index') }}">全条件クリア</a>
            @if ($nextUnlinkedSourceRecord)
                <a class="button small" href="{{ route('source-records.show', $nextUnlinkedSourceRecord) }}">先頭の未リンクを開く</a>
            @else
                <span class="button light small" style="opacity:.55;cursor:not-allowed;">未リンクなし</span>
            @endif
        </div>
    </div>
</div>

{{-- テーブル + 一括操作 --}}
<section class="card" style="padding:0; overflow:hidden;">
    <form method="POST" action="{{ route('source-records.bulk-create-companies') }}">
        @csrf
        @foreach (request()->except(['source_record_ids', '_token']) as $key => $value)
            @if (is_scalar($value) && $value !== null && $value !== '')
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach

        <div style="padding:16px 20px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <span style="font-size:13px; font-weight:900; color:var(--text);">source_records一覧</span>
                <span class="badge gray" style="margin-left:8px;">{{ number_format($sourceRecords->total()) }}件</span>
            </div>
            <button class="button small" type="submit" onclick="return confirm('チェックしたcompany化対象の未リンクsource_recordを一括company化する？リンク済みはスキップされる。');">選択分を一括company化</button>
        </div>

        <div class="table-wrap" style="border:none; border-radius:0; box-shadow:none;">
            <table>
                <thead>
                <tr>
                    <th><input type="checkbox" id="check-all-source-records" aria-label="全選択"></th>
                    <th><a href="{{ $sortUrl('id') }}">ID{{ $sortMark('id') }}</a></th>
                    <th><a href="{{ $sortUrl('source_type') }}">source_type{{ $sortMark('source_type') }}</a></th>
                    <th><a href="{{ $sortUrl('name_norm') }}">name_norm{{ $sortMark('name_norm') }}</a></th>
                    <th>業種</th>
                    <th><a href="{{ $sortUrl('normalized_domain') }}">domain{{ $sortMark('normalized_domain') }}</a></th>
                    <th><a href="{{ $sortUrl('pref_city') }}">pref/city{{ $sortMark('pref_city') }}</a></th>
                    <th><a href="{{ $sortUrl('fetched_at') }}">fetched_at{{ $sortMark('fetched_at') }}</a></th>
                    <th>状態</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($sourceRecords as $record)
                    @php
                        $isLinked = (bool) $record->sourceLink;
                    @endphp
                    <tr @if (!$isLinked && $record->id === ($firstUnlinkedId ?? null)) style="background:#fffbeb;" @endif>
                        <td>
                            <input
                                type="checkbox"
                                class="source-record-check"
                                name="source_record_ids[]"
                                value="{{ $record->id }}"
                                @disabled($isLinked)
                                aria-label="source_record #{{ $record->id }}を選択"
                            >
                        </td>
                        <td>{{ $record->id }}</td>
                        <td>{{ $record->source_type }}</td>
                        <td>{{ $record->name_norm ?? '-' }}</td>
                        <td>{{ data_get($record->raw_json, 'canonical.raw_industry') ?: data_get($record->raw_json, 'raw_industry', '-') }}</td>
                        <td style="min-width:180px;">
                            <div id="domain-display-{{ $record->id }}">
                                @if ($record->normalized_domain)
                                    @php
                                        $domHref = Str::startsWith($record->normalized_domain, 'http')
                                            ? $record->normalized_domain
                                            : 'https://' . $record->normalized_domain;
                                    @endphp
                                    <a href="{{ $domHref }}" target="_blank"
                                        style="font-size:12px;font-weight:700;color:var(--primary);text-decoration:none;word-break:break-all;">{{ $record->normalized_domain }}</a>
                                @else
                                    <span style="color:var(--muted);font-size:12px;">-</span>
                                @endif
                            </div>
                            @if ($record->source_url)
                                <div class="muted" style="max-width:300px;overflow-wrap:anywhere;font-size:11px;margin-top:2px;">{{ $record->source_url }}</div>
                            @endif
                            <div id="domain-edit-{{ $record->id }}" style="display:none;margin-top:6px;">
                                <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">
                                    <input type="text" id="domain-input-{{ $record->id }}"
                                        placeholder="https://example.com"
                                        value="{{ $record->normalized_domain ?? '' }}"
                                        style="height:28px;font-size:12px;padding:0 8px;border:1px solid #d9e2ee;border-radius:6px;min-width:150px;flex:1;">
                                    <button type="button" class="button small"
                                        onclick="saveDomainSr({{ $record->id }})">保存</button>
                                </div>
                            </div>
                        </td>
                        <td>{{ $record->pref ?? '-' }} / {{ $record->city ?? '-' }}</td>
                        <td>{{ optional($record->fetched_at)->format('Y-m-d H:i') ?? '-' }}</td>
                        <td>
                            @if ($isLinked)
                                <span class="badge green">company化済み</span>
                            @else
                                <span class="badge gray">未リンク</span>
                                @if ($record->id === ($firstUnlinkedId ?? null))
                                    <span class="badge" style="background:#f97316;color:#fff;border-color:#f97316;">次に処理</span>
                                @endif
                            @endif
                        </td>
                        <td style="white-space:nowrap;">
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <a class="button small light" href="{{ route('source-records.show', $record) }}">詳細</a>
                                <a class="button small light" target="_blank"
                                    href="https://www.google.com/search?q={{ urlencode(($record->name_norm ?? '') . ' ' . ($record->pref ?? '')) }}">WEB検索</a>
                                <button type="button" class="button small light"
                                    onclick="toggleDomainEditSr({{ $record->id }})">編集</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="empty-state">
                            <div class="empty-state-box">
                                <div class="empty-icon">SR</div>
                                <p class="empty-title">条件に合うsource_recordがない</p>
                                <p class="empty-copy">絞り込み条件をゆるめるか、CSV取り込み・手動登録からデータを追加して確認。</p>
                                <div class="empty-actions">
                                    <a class="button small light" href="{{ route('source-records.index') }}">条件クリア</a>
                                    <a class="button small" href="{{ route('source-records.import') }}">CSV取り込み</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </form>

    {{-- ページネーション --}}
    @php
        $paginator = $sourceRecords->appends(request()->query());
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $windowStart = max(1, $currentPage - 3);
        $windowEnd = min($lastPage, $currentPage + 3);
    @endphp
    @if ($lastPage > 1)
        <nav class="compact-pagination" style="padding:14px 20px;" aria-label="source_records pagination">
            <div class="pagination-links">
                @if ($paginator->onFirstPage())
                    <span class="page-link disabled">‹ Prev</span>
                @else
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}">‹ Prev</a>
                @endif
                @if ($windowStart > 1)
                    <a class="page-link" href="{{ $paginator->url(1) }}">1</a>
                    @if ($windowStart > 2)<span class="page-ellipsis">…</span>@endif
                @endif
                @for ($page = $windowStart; $page <= $windowEnd; $page++)
                    @if ($page === $currentPage)
                        <span class="page-link active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                    @endif
                @endfor
                @if ($windowEnd < $lastPage)
                    @if ($windowEnd < $lastPage - 1)<span class="page-ellipsis">…</span>@endif
                    <a class="page-link" href="{{ $paginator->url($lastPage) }}">{{ $lastPage }}</a>
                @endif
                @if ($paginator->hasMorePages())
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}">Next ›</a>
                @else
                    <span class="page-link disabled">Next ›</span>
                @endif
            </div>
            <div class="pagination-count">
                {{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }} / {{ number_format($paginator->total()) }}件
            </div>
        </nav>
    @endif
</section>

{{-- 一括処理バー（チェック時に画面下部に固定表示） --}}
<div id="sr-bulk-bar" class="sr-bulk-bar" style="display:none;">
    <strong id="sr-bulk-count">0件選択中</strong>
    <button class="button small btn-exclude" type="button" id="sr-bulk-exclude">kill_flag化（除外）</button>
    <button class="button small btn-delete" type="button" id="sr-bulk-delete">DELETE（削除）</button>
    <button class="button small btn-deselect" type="button" id="sr-bulk-deselect">選択解除</button>
</div>

<form id="sr-bulk-exclude-form" method="POST" action="{{ route('source-records.bulk-exclude') }}" style="display:none;">
    @csrf
</form>
<form id="sr-bulk-delete-form" method="POST" action="{{ route('source-records.bulk-delete') }}" style="display:none;">
    @csrf
    @method('DELETE')
</form>

</main>

@push('scripts')
<script>
const SR_PREF_DATA     = @json($prefectures->map(fn($p) => ['name' => $p->name, 'cities' => $p->municipalities->pluck('name')]));
const SR_SELECTED_PREF = @json(request('pref', ''));
const SR_SELECTED_CITY = @json(request('city', ''));

function toggleDomainEditSr(id) {
    const el = document.getElementById('domain-edit-' + id);
    if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function saveDomainSr(id) {
    const input = document.getElementById('domain-input-' + id);
    const url   = input ? input.value.trim() : '';
    fetch('/source-records/' + id + '/domain', {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ url }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.ok) return;
        const display = document.getElementById('domain-display-' + id);
        if (display) {
            if (data.normalized_domain) {
                const href = data.normalized_domain.startsWith('http')
                    ? data.normalized_domain
                    : 'https://' + data.normalized_domain;
                display.innerHTML = `<a href="${href}" target="_blank" style="font-size:12px;font-weight:700;color:var(--primary);text-decoration:none;word-break:break-all;">${data.normalized_domain}</a>`;
            } else {
                display.innerHTML = '<span style="color:var(--muted);font-size:12px;">-</span>';
            }
        }
        const editDiv = document.getElementById('domain-edit-' + id);
        if (editDiv) editDiv.style.display = 'none';
    })
    .catch(err => alert('更新失敗: ' + err.message));
}
</script>
@endpush
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const srPrefSelect = document.getElementById('pref-select-sr');
    const srCitySelect = document.getElementById('city-select-sr');

    function populateSrCities(prefName, selectedCity) {
        srCitySelect.innerHTML = '<option value="">すべて</option>';
        const pref = SR_PREF_DATA.find(p => p.name === prefName);
        if (pref) {
            pref.cities.forEach(city => {
                const opt = document.createElement('option');
                opt.value = city;
                opt.textContent = city;
                if (city === selectedCity) opt.selected = true;
                srCitySelect.appendChild(opt);
            });
        }
    }

    if (srPrefSelect) {
        srPrefSelect.addEventListener('change', function () {
            populateSrCities(this.value, '');
        });
        if (SR_SELECTED_PREF) {
            populateSrCities(SR_SELECTED_PREF, SR_SELECTED_CITY);
        }
    }
});
</script>
@endpush
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkAll    = document.getElementById('check-all-source-records');
    const bar         = document.getElementById('sr-bulk-bar');
    const countLabel  = document.getElementById('sr-bulk-count');
    const deselect    = document.getElementById('sr-bulk-deselect');
    const excludeBtn  = document.getElementById('sr-bulk-exclude');
    const deleteBtn   = document.getElementById('sr-bulk-delete');
    const excludeForm = document.getElementById('sr-bulk-exclude-form');
    const deleteForm  = document.getElementById('sr-bulk-delete-form');

    function getChecked() { return Array.from(document.querySelectorAll('.source-record-check:checked')); }
    function getEnabled() { return Array.from(document.querySelectorAll('.source-record-check:not(:disabled)')); }

    function injectIds(form, checked) {
        form.querySelectorAll('.sr-injected-id').forEach(el => el.remove());
        checked.forEach(function (cb) {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'source_record_ids[]';
            input.value = cb.value; input.className = 'sr-injected-id';
            form.appendChild(input);
        });
    }

    function updateBar() {
        const checked = getChecked();
        bar.style.display = checked.length > 0 ? 'flex' : 'none';
        countLabel.textContent = checked.length + '件選択中';
        if (checkAll) {
            const enabled = getEnabled();
            checkAll.checked       = enabled.length > 0 && enabled.every(cb => cb.checked);
            checkAll.indeterminate = checked.length > 0 && !checkAll.checked;
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            getEnabled().forEach(cb => { cb.checked = checkAll.checked; });
            updateBar();
        });
    }

    document.querySelectorAll('.source-record-check').forEach(cb => {
        cb.addEventListener('change', updateBar);
    });

    if (deselect) {
        deselect.addEventListener('click', function () {
            getEnabled().forEach(cb => { cb.checked = false; });
            if (checkAll) { checkAll.checked = false; checkAll.indeterminate = false; }
            updateBar();
        });
    }

    if (excludeBtn) {
        excludeBtn.addEventListener('click', function () {
            const checked = getChecked();
            if (!checked.length) return;
            if (!confirm(checked.length + '件をkill_flag化（is_excluded=true）にする？')) return;
            injectIds(excludeForm, checked);
            excludeForm.submit();
        });
    }

    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            const checked = getChecked();
            if (!checked.length) return;
            if (!confirm('選択した' + checked.length + '件を削除しますか？')) return;
            injectIds(deleteForm, checked);
            deleteForm.submit();
        });
    }
});
</script>
@endpush
@endsection
