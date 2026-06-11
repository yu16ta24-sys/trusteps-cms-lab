@extends('layouts.app')

@section('title', '全国商工会HTML一括取込')

@section('content')
@php
    $summary = $preview['summary'] ?? null;
    $prefGroups = $preview['pref_groups'] ?? [];
@endphp

<div class="page-stack">
    <section class="card-panel">
        <div class="section-kicker">SHOKOKAI BULK HTML IMPORT</div>
        <div class="section-header-row">
            <div>
                <h1 class="page-title">全国商工会HTML一括取込</h1>
                <p class="page-description">
                    全国商工会WEBサーチで全件表示したHTMLを貼り付け、商工会HPを都道府県別に抽出します。
                    営業先companyは作らず、名簿元候補としてsource_recordsに保存します。
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="alert success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('directory-sources.shokokai-bulk-html.preview') }}" class="form-stack">
            @csrf
            <label class="form-label" for="html">全件表示HTML</label>
            <textarea id="html" name="html" rows="14" class="form-textarea" placeholder="<li>...商工会データ...</li> を含むHTMLを貼り付け">{{ old('html', $htmlInput ?? '') }}</textarea>
            <p class="form-hint">
                1600件前後のHTML貼り付けを想定。外部サイトにはアクセスせず、貼り付けられたHTMLだけを解析します。
            </p>
            <div class="button-row">
                <button type="submit" class="btn-primary">プレビュー生成</button>
                <a href="{{ route('directory-sources.shokokai-bulk-html') }}" class="btn-secondary">リセット</a>
            </div>
        </form>
    </section>

    @if ($preview)
        <section class="card-panel">
            <div class="section-kicker">PREVIEW</div>
            <h2 class="section-title">抽出結果サマリー</h2>

            <div class="metric-grid compact">
                <div class="metric-card">
                    <div class="metric-value">{{ number_format($summary['total'] ?? 0) }}</div>
                    <div class="metric-label">総件数</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">{{ number_format($summary['valid_url'] ?? 0) }}</div>
                    <div class="metric-label">有効URL</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">{{ number_format($summary['no_url'] ?? 0) }}</div>
                    <div class="metric-label">URLなし</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">{{ number_format($summary['invalid_url'] ?? 0) }}</div>
                    <div class="metric-label">URL要確認</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">{{ number_format($summary['duplicate'] ?? 0) }}</div>
                    <div class="metric-label">重複/注意</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">{{ number_format($summary['pref_count'] ?? 0) }}</div>
                    <div class="metric-label">都道府県数</div>
                </div>
            </div>

            <div class="summary-pills">
                <span class="pill">地域商工会 {{ number_format($summary['local_shokokai'] ?? 0) }}</span>
                <span class="pill">都道府県連合会 {{ number_format($summary['pref_federation'] ?? 0) }}</span>
                <span class="pill">全国連合会 {{ number_format($summary['national_federation'] ?? 0) }}</span>
                <span class="pill primary">初期チェック {{ number_format($summary['default_checked'] ?? 0) }}</span>
            </div>
        </section>

        <form method="POST" action="{{ route('directory-sources.shokokai-bulk-html.store') }}" class="form-stack" id="bulkSaveForm">
            @csrf
            <input type="hidden" name="token" value="{{ $preview['token'] }}">

            <section class="card-panel">
                <div class="section-header-row">
                    <div>
                        <div class="section-kicker">PREFECTURE GROUPS</div>
                        <h2 class="section-title">都道府県別プレビュー</h2>
                    </div>
                    <div class="button-row small">
                        <button type="button" class="btn-secondary" data-check-action="all-on">全体を全チェック</button>
                        <button type="button" class="btn-secondary" data-check-action="all-off">全体を全解除</button>
                    </div>
                </div>

                <div class="accordion-list">
                    @foreach ($prefGroups as $groupIndex => $group)
                        @php
                            $groupId = 'pref-group-' . $groupIndex;
                            $groupSummary = $group['summary'] ?? [];
                        @endphp
                        <details class="accordion-card" @if ($groupIndex < 3) open @endif>
                            <summary class="accordion-summary">
                                <span class="accordion-title">{{ $group['pref_label'] ?? '不明' }}</span>
                                <span class="accordion-meta">
                                    総数 {{ number_format($groupSummary['total'] ?? 0) }} / 有効 {{ number_format($groupSummary['valid_url'] ?? 0) }} / URLなし {{ number_format($groupSummary['no_url'] ?? 0) }} / 要確認 {{ number_format($groupSummary['invalid_url'] ?? 0) }}
                                </span>
                            </summary>

                            <div class="accordion-body">
                                <div class="button-row small">
                                    <button type="button" class="btn-secondary" data-check-action="group-on" data-group="{{ $groupId }}">この県を全チェック</button>
                                    <button type="button" class="btn-secondary" data-check-action="group-off" data-group="{{ $groupId }}">この県を全解除</button>
                                </div>

                                <div class="table-wrap">
                                    <table class="data-table compact-table">
                                        <thead>
                                            <tr>
                                                <th>保存</th>
                                                <th>名称</th>
                                                <th>URL / domain</th>
                                                <th>住所・連絡先</th>
                                                <th>分類</th>
                                                <th>状態</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach (($group['rows'] ?? []) as $row)
                                                @php
                                                    $disabled = empty($row['storable']);
                                                    $checked = !empty($row['default_checked']) && !$disabled;
                                                    $signals = $row['duplicate_signals'] ?? [];
                                                @endphp
                                                <tr>
                                                    <td class="nowrap">
                                                        <input type="checkbox"
                                                            name="selected_rows[]"
                                                            value="{{ $row['row_id'] }}"
                                                            class="bulk-row-checkbox {{ $groupId }}"
                                                            @checked($checked)
                                                            @disabled($disabled)>
                                                    </td>
                                                    <td>
                                                        <div class="strong">{{ $row['organization_name'] ?? '-' }}</div>
                                                        <div class="muted small-text">No.{{ $row['raw_index'] ?? '-' }} / code {{ $row['pref_code'] ?? '-' }}-{{ $row['shokokai_code'] ?? '-' }}</div>
                                                    </td>
                                                    <td>
                                                        @if (!empty($row['url']))
                                                            <a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ $row['url'] }}</a>
                                                            <div class="muted small-text">{{ $row['normalized_domain'] ?? '-' }}</div>
                                                        @elseif (!empty($row['raw_url']))
                                                            <div class="danger-text">{{ $row['raw_url'] }}</div>
                                                        @else
                                                            <span class="muted">URLなし</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div>{{ $row['postal_code'] ?? '' }} {{ $row['address'] ?? '-' }}</div>
                                                        <div class="muted small-text">TEL {{ $row['tel'] ?? '-' }} / FAX {{ $row['fax'] ?? '-' }}</div>
                                                    </td>
                                                    <td>
                                                        <span class="pill">{{ $row['organization_type_label'] ?? '-' }}</span>
                                                        <div class="muted small-text">{{ $row['category_label'] ?? '-' }}</div>
                                                        <div class="small-text">信頼度：{{ $row['confidence_label'] ?? '-' }}</div>
                                                    </td>
                                                    <td>
                                                        @if (($row['status_key'] ?? '') === 'valid_url')
                                                            <span class="badge success">{{ $row['status_label'] ?? '有効URL' }}</span>
                                                        @elseif (($row['status_key'] ?? '') === 'no_url')
                                                            <span class="badge muted-badge">URLなし</span>
                                                        @else
                                                            <span class="badge warning">{{ $row['status_label'] ?? '要確認' }}</span>
                                                        @endif

                                                        @if (!empty($signals))
                                                            <div class="warning-list small-text">
                                                                @foreach ($signals as $signal)
                                                                    <div>・{{ $signal }}</div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>

            <div class="sticky-action-bar">
                <button type="submit" class="btn-primary">選択分をsource_recordsへ保存</button>
                <a href="{{ route('directory-sources.shokokai-bulk-html') }}" class="btn-secondary">プレビュー破棄</a>
            </div>
        </form>
    @endif
</div>

<script>
(function () {
    function setChecked(selector, checked) {
        document.querySelectorAll(selector).forEach(function (checkbox) {
            if (!checkbox.disabled) {
                checkbox.checked = checked;
            }
        });
    }

    document.querySelectorAll('[data-check-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            var action = button.getAttribute('data-check-action');
            var group = button.getAttribute('data-group');

            if (action === 'all-on') {
                setChecked('.bulk-row-checkbox', true);
            }
            if (action === 'all-off') {
                setChecked('.bulk-row-checkbox', false);
            }
            if (action === 'group-on' && group) {
                setChecked('.' + group, true);
            }
            if (action === 'group-off' && group) {
                setChecked('.' + group, false);
            }
        });
    });
})();
</script>
@endsection
