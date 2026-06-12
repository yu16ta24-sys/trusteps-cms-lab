@extends('layouts.app', ['title' => '業界スコア CSV入出力 | TRUSTEPS CMS Lab'])

@section('content')
<main class="content isc">

<style>
.isc { display:grid; gap:20px; }
.isc-topbar { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px; }
.isc-kicker { font-size:11px; font-weight:900; color:var(--muted); letter-spacing:.1em; text-transform:uppercase; margin-bottom:6px; }
.isc-title  { margin:0; font-size:28px; font-weight:950; letter-spacing:-.03em; color:var(--text); }
.isc-sub    { margin:5px 0 0; font-size:13px; color:var(--muted); }
.isc-format-table { width:100%; border-collapse:collapse; font-size:12px; margin-top:10px; }
.isc-format-table th { padding:6px 10px; background:#f8fafc; text-align:left; font-size:11px; font-weight:900; letter-spacing:.04em; border-bottom:1px solid var(--line); }
.isc-format-table td { padding:6px 10px; border-bottom:1px solid #f0f4f8; font-family:monospace; font-size:12px; }
.isc-req { color:#dc2626; font-weight:900; }
.preview-stat { display:inline-flex; gap:6px; align-items:center; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:900; }
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
{{-- ===== プレビュー ===== --}}
@php
    $newCount     = collect($rows)->where('is_new', true)->count();
    $changedCount = collect($rows)->where('is_changed', true)->count();
    $sameCount    = collect($rows)->where('is_new', false)->where('is_changed', false)->count();
@endphp
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div>
            <p class="section-label" style="margin:0 0 4px;">Import Preview</p>
            <h2 style="margin:0;font-size:20px;">インポートプレビュー</h2>
            <p style="margin:6px 0 0;font-size:13px;color:var(--muted);">内容を確認して「確定インポート」を実行してください。</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <span class="badge green">新規 {{ $newCount }}件</span>
            <span class="badge amber">更新 {{ $changedCount }}件</span>
            <span class="badge gray">同値 {{ $sameCount }}件</span>
        </div>
    </div>

    <div class="table-wrap" style="max-height:60vh;overflow-y:auto;">
        <table>
            <thead>
                <tr>
                    <th>状態</th>
                    <th>業種</th>
                    <th>評価軸</th>
                    <th style="text-align:center;">現在値</th>
                    <th style="text-align:center;">新しい値</th>
                    <th>confidence</th>
                    <th>score_type</th>
                    <th>note</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>
                        @if ($row['is_new'])
                            <span class="badge green">NEW</span>
                        @elseif ($row['is_changed'])
                            <span class="badge amber">更新</span>
                        @else
                            <span class="badge gray">同値</span>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:900;font-size:13px;">{{ $row['industry_name'] }}</div>
                        <div style="font-size:11px;color:var(--muted);font-family:monospace;">{{ $row['industry_key'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;">{{ $row['axis_label'] }}</div>
                        <div style="font-size:11px;color:var(--muted);font-family:monospace;">{{ $row['axis_key'] }}</div>
                    </td>
                    <td style="text-align:center;color:var(--muted);">{{ $row['current_value'] ?? '—' }}</td>
                    <td style="text-align:center;font-weight:950;font-size:15px;">{{ $row['value'] }}</td>
                    <td style="font-size:12px;">{{ $row['confidence'] ?? '—' }}</td>
                    <td style="font-size:12px;">{{ $row['score_type'] }}</td>
                    <td style="font-size:12px;max-width:200px;word-break:break-all;">{{ $row['note'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div style="display:flex;gap:10px;margin-top:16px;align-items:center;">
        <form method="POST" action="{{ route('industries.scores.import.store') }}">
            @csrf
            <button class="button" type="submit">確定インポート（{{ count($rows) }}件）</button>
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
    <p class="section-label" style="margin:0 0 12px;">CSVフォーマット</p>
    <p style="font-size:13px;color:var(--muted);margin-bottom:10px;">
        1行 = 1業種 × 1軸のスコア。<span class="isc-req">*</span> は必須列。<code>industry_name</code> / <code>axis_label</code> はエクスポートの参照列で、インポート時は無視されます。<br>
        <code>value</code> が空欄の行はスキップ（既存レコードを削除しません）。
    </p>
    <div class="table-wrap">
        <table class="isc-format-table">
            <thead>
                <tr>
                    <th>列名</th>
                    <th>必須</th>
                    <th>値の例</th>
                    <th>説明</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>industry_key</td><td><span class="isc-req">*</span></td><td>food</td><td>業種スラッグ（子業種のみ）</td></tr>
                <tr><td>industry_name</td><td>—</td><td>飲食</td><td>参照用。インポートでは無視</td></tr>
                <tr><td>axis_key</td><td><span class="isc-req">*</span></td><td>market_size</td><td>評価軸キー</td></tr>
                <tr><td>axis_label</td><td>—</td><td>市場規模</td><td>参照用。インポートでは無視</td></tr>
                <tr><td>value</td><td><span class="isc-req">*</span></td><td>3</td><td>スコア 0〜5 の整数。空欄はスキップ</td></tr>
                <tr><td>confidence</td><td>—</td><td>medium</td><td>low / medium / high（空欄可）</td></tr>
                <tr><td>score_type</td><td>—</td><td>hypothesis</td><td>hypothesis / observed / mixed（空欄時 hypothesis）</td></tr>
                <tr><td>note</td><td>—</td><td>根拠メモ</td><td>任意テキスト</td></tr>
            </tbody>
        </table>
    </div>

    <div style="margin-top:14px;">
        <a class="button light small" href="{{ route('industries.scores.export') }}">現在のデータをCSVエクスポート（テンプレートとして利用可）</a>
    </div>
</section>
@endisset

</main>
@endsection
