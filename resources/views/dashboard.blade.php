@extends('layouts.app', ['title' => 'Dashboard | TRUSTEPS CMS Lab'])

@section('content')
<main class="content db">
<style>
.db { display:grid; gap:20px; }
.db-topbar { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px; }
.db-kicker { font-size:11px; font-weight:900; color:var(--muted); letter-spacing:.1em; text-transform:uppercase; margin-bottom:6px; }
.db-title { margin:0; font-size:28px; font-weight:950; letter-spacing:-.03em; color:var(--text); }
.db-sub { margin:5px 0 0; font-size:13px; color:var(--muted); }
.db-btn-row { display:flex; gap:8px; flex-wrap:wrap; }
.db-sec-label { font-size:10px; font-weight:900; color:var(--muted); letter-spacing:.1em; text-transform:uppercase; margin-bottom:14px; }
.db-next-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
.db-next-card { border:1px solid var(--line); border-radius:18px; padding:18px; background:#fff; display:flex; flex-direction:column; }
.db-step { font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; padding:3px 9px; border-radius:999px; display:inline-block; width:fit-content; margin-bottom:10px; }
.db-step-blue { background:#dbeafe; color:#1d4ed8; }
.db-step-gray { background:#f2f4f7; color:#475467; }
.db-step-amber { background:#fef3c7; color:#92400e; }
.db-step-green { background:#dcfce7; color:#166534; }
.db-next-num { font-size:38px; font-weight:950; color:var(--text); letter-spacing:-.04em; line-height:1; margin:6px 0 4px; }
.db-next-desc { font-size:12px; color:var(--muted); line-height:1.5; flex:1; }
.db-next-action { margin-top:14px; }
.db-work-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
.db-work-card { border:1px solid var(--line); border-radius:18px; padding:18px; background:#fff; }
.db-work-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; }
.db-work-title { font-size:13px; font-weight:900; color:var(--text); }
.db-work-desc { font-size:11px; color:var(--muted); margin-bottom:10px; line-height:1.5; }
.db-item { padding:9px 0; border-top:1px solid var(--line); }
.db-item-name { font-size:12px; font-weight:900; color:var(--text); margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.db-item-sub { font-size:11px; color:var(--muted); }
.db-item-btn { margin-top:6px; }
.db-cand-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
.db-cand-stat { background:#f8fafc; border:1px solid var(--line); border-radius:16px; padding:14px 16px; }
.db-cand-label { font-size:11px; color:var(--muted); font-weight:900; margin-bottom:6px; }
.db-cand-num { font-size:28px; font-weight:950; color:var(--text); letter-spacing:-.03em; line-height:1; }
.db-cand-sub { font-size:11px; color:var(--muted); margin-top:4px; }
.db-card-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
@media(max-width:900px){
    .db-next-grid { grid-template-columns:repeat(2,1fr); }
    .db-work-grid { grid-template-columns:1fr; }
    .db-cand-grid { grid-template-columns:repeat(2,1fr); }
}
@media(max-width:600px){
    .db-next-grid { grid-template-columns:1fr; }
    .db-cand-grid { grid-template-columns:1fr; }
}
</style>

<div class="db-topbar">
    <div>
        <div class="db-kicker">TRUSTEPS CMS Lab</div>
        <h1 class="db-title">Dashboard</h1>
        <p class="db-sub">データ投入・採点・候補抽出の進捗</p>
    </div>
    <div class="db-btn-row">
        <a class="button light small" href="{{ route('source-records.index') }}">source_records</a>
        <a class="button light small" href="{{ route('companies.index') }}">companies</a>
        <a class="button small" href="{{ route('companies.candidates') }}">営業候補 →</a>
    </div>
</div>

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

{{-- Next action --}}
<section class="card">
    <div class="db-sec-label">Next action</div>
    <div class="db-next-grid">
        <div class="db-next-card">
            <span class="db-step db-step-blue">1 · company化待ち</span>
            <div class="db-next-num">{{ number_format($summary['source_records']['unlinked']) }}</div>
            <div class="db-next-desc">未リンク source_records</div>
            <div class="db-next-action">
                <a class="button small" href="{{ route('source-records.index', ['link_status' => 'unlinked']) }}">処理する →</a>
            </div>
        </div>
        <div class="db-next-card">
            <span class="db-step db-step-gray">2 · 未採点</span>
            <div class="db-next-num">{{ number_format($summary['scores']['unscored']) }}</div>
            <div class="db-next-desc">4軸スコア未入力</div>
            <div class="db-next-action">
                <a class="button light small" href="{{ route('companies.index', ['score_state' => 'unscored']) }}">見る →</a>
            </div>
        </div>
        <div class="db-next-card">
            <span class="db-step db-step-amber">3 · 採点待ち候補</span>
            <div class="db-next-num">{{ number_format($summary['candidates']['needs_scoring']) }}</div>
            <div class="db-next-desc">候補一覧で4軸不足</div>
            <div class="db-next-action">
                <a class="button light small" href="{{ route('companies.candidates', ['preset' => 'needs_scoring']) }}">見る →</a>
            </div>
        </div>
        <div class="db-next-card">
            <span class="db-step db-step-green">4 · 推奨候補</span>
            <div class="db-next-num">{{ number_format($summary['candidates']['recommended']) }}</div>
            <div class="db-next-desc">高機会・低リスク</div>
            <div class="db-next-action">
                <a class="button small" href="{{ route('companies.candidates', ['preset' => 'recommended']) }}">確認する →</a>
            </div>
        </div>
    </div>
</section>

{{-- Today's board --}}
<section class="card">
    <div class="db-card-head">
        <div class="db-sec-label" style="margin:0">Today's board</div>
        <span style="font-size:11px;color:var(--muted)">各最大5件</span>
    </div>
    <div class="db-work-grid">

        {{-- 次の source_records --}}
        <div class="db-work-card">
            <div class="db-work-head">
                <div class="db-work-title">次の source_records</div>
                <span class="db-step db-step-blue" style="margin:0">未リンク</span>
            </div>
            <div class="db-work-desc">まずcompany化する候補。IDが古い順。</div>
            @forelse ($workBoard['next_source_records'] as $record)
                @php
                    $rawName = data_get($record->raw_json, 'canonical.raw_name')
                        ?? data_get($record->raw_json, 'company_name')
                        ?? $record->name_norm
                        ?? ('source_record #' . $record->id);
                    $region = trim(($record->pref ?? '') . ' ' . ($record->city ?? ''));
                @endphp
                <div class="db-item">
                    <div class="db-item-name">#{{ $record->id }} {{ $rawName }}</div>
                    <div class="db-item-sub">{{ $region !== '' ? $region : '地域未設定' }} · {{ $record->normalized_domain ?: 'domainなし' }}</div>
                    <div class="db-item-btn">
                        <a class="button light small" href="{{ route('source-records.show', $record) }}">開く</a>
                    </div>
                </div>
            @empty
                <div class="db-item" style="text-align:center;padding:18px 0;color:var(--muted);font-size:13px;">
                    未リンクのsource_recordはありません
                </div>
            @endforelse
        </div>

        {{-- 次の採点対象 --}}
        <div class="db-work-card">
            <div class="db-work-head">
                <div class="db-work-title">次の採点対象</div>
                <span class="db-step db-step-gray" style="margin:0">4軸不足</span>
            </div>
            <div class="db-work-desc">スコアが足りないcompany。未採点に近い順。</div>
            @forelse ($workBoard['scoring_queue'] as $company)
                <div class="db-item">
                    <div class="db-item-name">#{{ $company->id }} {{ $company->display_name ?? $company->legal_name ?? '名称未設定' }}</div>
                    <div class="db-item-sub">採点 {{ $company->dashboard_scored_axes_count }} / 4</div>
                    <div class="db-item-btn">
                        <a class="button light small" href="{{ route('companies.show', $company) }}">採点する</a>
                    </div>
                </div>
            @empty
                <div class="db-item" style="text-align:center;padding:18px 0;color:var(--muted);font-size:13px;">
                    採点待ちはありません
                </div>
            @endforelse
        </div>

        {{-- 推奨候補 TOP --}}
        <div class="db-work-card">
            <div class="db-work-head">
                <div class="db-work-title">推奨候補 TOP</div>
                <span class="db-step db-step-green" style="margin:0">高機会・低リスク</span>
            </div>
            <div class="db-work-desc">4軸採点済みの中で優先確認したい候補。</div>
            @forelse ($workBoard['recommended_queue'] as $company)
                <div class="db-item">
                    <div class="db-item-name">#{{ $company->id }} {{ $company->display_name ?? $company->legal_name ?? '名称未設定' }}</div>
                    <div class="db-item-sub">機会 {{ $company->dashboard_opportunity_score }} · リスク {{ $company->dashboard_risk_score }}</div>
                    <div class="db-item-btn">
                        <a class="button small" href="{{ route('companies.show', $company) }}">詳細</a>
                    </div>
                </div>
            @empty
                <div class="db-item" style="text-align:center;padding:18px 0;color:var(--muted);font-size:13px;">
                    推奨候補はまだありません
                </div>
            @endforelse
        </div>

    </div>
</section>

{{-- 営業候補の状態 --}}
<section class="card">
    <div class="db-card-head">
        <div class="db-sec-label" style="margin:0">営業候補の状態</div>
        <div class="db-btn-row">
            <a class="button small" href="{{ route('companies.candidates', ['preset' => 'recommended']) }}">推奨候補 →</a>
            <a class="button light small" href="{{ route('companies.candidates', ['preset' => 'needs_scoring']) }}">採点待ち</a>
        </div>
    </div>
    <div class="db-cand-grid">
        <div class="db-cand-stat">
            <div class="db-cand-label">active候補</div>
            <div class="db-cand-num">{{ number_format($summary['candidates']['total']) }}</div>
        </div>
        <div class="db-cand-stat">
            <div class="db-cand-label">推奨</div>
            <div class="db-cand-num">{{ number_format($summary['candidates']['recommended']) }}</div>
            <div class="db-cand-sub">高機会・低リスク</div>
        </div>
        <div class="db-cand-stat">
            <div class="db-cand-label">高機会</div>
            <div class="db-cand-num">{{ number_format($summary['candidates']['high_opportunity']) }}</div>
        </div>
        <div class="db-cand-stat">
            <div class="db-cand-label">採点待ち</div>
            <div class="db-cand-num">{{ number_format($summary['candidates']['needs_scoring']) }}</div>
        </div>
    </div>
</section>

</main>
@endsection
