@extends('layouts.app', ['title' => '業界スコア CSV入出力 | TRUSTEPS CMS Lab'])

@section('content')
<main class="content isc">

<style>
.isc { display:grid; gap:20px; }
.isc-topbar { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px; }
.isc-kicker { font-size:11px; font-weight:900; color:var(--muted); letter-spacing:.1em; text-transform:uppercase; margin-bottom:6px; }
.isc-title  { margin:0; font-size:28px; font-weight:950; letter-spacing:-.03em; color:var(--text); }
.isc-sub    { margin:5px 0 0; font-size:13px; color:var(--muted); }
.isc-req    { color:#dc2626; font-weight:900; }
.isc-matrix th { white-space:nowrap; font-size:11px; }
.isc-matrix td { text-align:center; font-size:13px; padding:6px 10px; }
.isc-matrix td:first-child { text-align:left; white-space:nowrap; }
</style>

<div class="isc-topbar">
    <div>
        <div class="isc-kicker">Industry Scores · CSV 入出力</div>
        <h1 class="isc-title">業界スコア CSV入出力</h1>
        <p class="isc-sub">CSVで業界スコアを一括インポート・エクスポートできます。</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <a class="button light small" href="{{ route('industries.scores.export') }}">CSVエクスポート</a>
        <a class="button light small" href="{{ route('industries.scores.index') }}">業界スコア一覧</a>
        <a class="button light small" href="{{ route('dashboard') }}">Dashboard</a>
    </div>
</div>

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="error" style="white-space:pre-wrap;">{{ implode("\n", $errors->all()) }}</div>
@endif

@isset($rows)
{{-- ===== プレビュー（横持ちマトリックス） ===== --}}
@php
    $axisKeysInRows  = collect($rows)->pluck('axis_key')->unique()->values();
    $axisLabelMap    = collect($rows)->pluck('axis_label', 'axis_key');
    $matrix          = collect($rows)->groupBy('industry_key');
    $industryNameMap = collect($rows)->pluck('industry_name', 'industry_key');
    $newCount        = collect($rows)->where('is_new', true)->count();
    $changedCount    = collect($rows)->where('is_changed', true)->count();
    $sameCount       = collect($rows)->where('is_new', false)->where('is_changed', false)->count();
@endphp

<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div>
            <p class="section-label" style="margin:0 0 4px;">Import Preview</p>
            <h2 style="margin:0;font-size:20px;">インポートプレビュー</h2>
            <p style="margin:6px 0 0;font-size:13px;color:var(--muted);">内容を確認して「確定インポート」を実行してください。空白セルはスキップ（既存値を保持）します。</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <span class="badge green">新規 {{ $newCount }}件</span>
            <span class="badge amber">更新 {{ $changedCount }}件</span>
            <span class="badge gray">同値 {{ $sameCount }}件</span>
        </div>
    </div>

    <div class="table-wrap" style="max-height:65vh;overflow:auto;">
        <table class="isc-matrix">
            <thead>
                <tr>
                    <th style="text-align:left;">業種</th>
                    @foreach ($axisKeysInRows as $axisKey)
                        <th>
                            <div>{{ $axisLabelMap[$axisKey] ?? $axisKey }}</div>
                            <div style="font-family:monospace;font-weight:400;font-size:10px;color:var(--muted);">{{ $axisKey }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @foreach ($matrix as $industryKey => $industryRows)
                @php $byAxis = $industryRows->keyBy('axis_key'); @endphp
                <tr>
                    <td>
                        <div style="font-weight:900;font-size:13px;">{{ $industryNameMap[$industryKey] ?? $industryKey }}</div>
                        <div style="font-size:11px;color:var(--muted);font-family:monospace;">{{ $industryKey }}</div>
                    </td>
                    @foreach ($axisKeysInRows as $axisKey)
                        @php $cell = $byAxis->get($axisKey); @endphp
                        <td>
                            @if ($cell)
                                @if ($cell['is_new'])
                                    <span class="badge green" style="font-size:12px;">{{ $cell['value'] }}</span>
                                @elseif ($cell['is_changed'])
                                    <span class="badge amber" style="font-size:11px;white-space:nowrap;">{{ $cell['current_value'] }}→{{ $cell['value'] }}</span>
                                @else
                                    <span style="font-weight:700;color:var(--muted);">{{ $cell['value'] }}</span>
                                @endif
                            @else
                                <span style="color:#d0d5dd;">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div style="display:flex;gap:10px;margin-top:16px;align-items:center;">
        <form method="POST" action="{{ route('industries.scores.import.store') }}">
            @csrf
            <button class="button" type="submit">確定インポート（{{ count($rows) }}セル）</button>
        </form>
        <a class="button light" href="{{ route('industries.scores.import') }}">キャンセル</a>
    </div>
</section>

@else
{{-- ===== アップロードフォーム ===== --}}
<section class="card">
    <p class="section-label" style="margin:0 0 12px;">CSV インポート</p>
    <form method="POST" action="{{ route('industries.scores.import.preview') }}" enctype="multipart/form-data">
        @csrf
        <div class="field" style="max-width:480px;">
            <label for="csv_file">CSVファイル（.csv）</label>
            <input id="csv_file" type="file" name="csv_file" accept=".csv,.txt" required>
        </div>
        <div class="actions" style="margin-top:12px;">
            <button class="button" type="submit">プレビューを確認</button>
        </div>
    </form>
</section>

<section class="card">
    <p class="section-label" style="margin:0 0 10px;">CSVフォーマット（横持ち・マトリックス形式）</p>
    <p style="font-size:13px;color:var(--muted);margin-bottom:12px;">
        1行 = 1業種。各軸の値を列に並べるマトリックス形式。空白セルはスキップ（既存スコアを削除しません）。<br>
        エクスポートCSVをそのままテンプレートとして使えます。<code>industry_name</code> 列はインポート時に無視されます。
    </p>
    <div style="background:#f8fafc;border:1px solid var(--line);border-radius:12px;padding:14px 16px;font-family:monospace;font-size:12px;overflow-x:auto;white-space:pre;">industry_key,industry_name,{{ $axisKeys ?? 'axis_key1,axis_key2,...' }}
food,飲食,3,2,...
it,IT,4,5,...</div>
    <div style="margin-top:14px;">
        <a class="button light small" href="{{ route('industries.scores.export') }}">現在のデータをCSVエクスポート（テンプレートとして利用可）</a>
    </div>
</section>
@endisset

</main>
@endsection
