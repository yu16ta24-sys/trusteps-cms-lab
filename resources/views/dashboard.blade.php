@extends('layouts.app', ['title' => 'ダッシュボード | TRUSTEPS CMS Lab'])

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
.db-rank { font-size:10px; font-weight:900; padding:2px 8px; border-radius:999px; display:inline-block; }
.db-rank-S { background:#fae8ff; color:#86198f; }
.db-rank-A { background:#dcfce7; color:#166534; }
.db-rank-B { background:#dbeafe; color:#1d4ed8; }
.db-rank-C { background:#f2f4f7; color:#475467; }
.db-rank-D { background:#fee2e2; color:#991b1b; }
.db-cand-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; }
.db-cand-stat { background:#f8fafc; border:1px solid var(--line); border-radius:16px; padding:14px 16px; }
.db-cand-label { font-size:11px; color:var(--muted); font-weight:900; margin-bottom:6px; }
.db-cand-num { font-size:28px; font-weight:950; color:var(--text); letter-spacing:-.03em; line-height:1; }
.db-cand-sub { font-size:11px; color:var(--muted); margin-top:4px; }
.db-type-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-top:12px; }
.db-type-stat { border:1px solid var(--line); border-radius:14px; padding:12px 14px; }
.db-type-label { font-size:10px; color:var(--muted); font-weight:900; margin-bottom:5px; }
.db-type-num { font-size:22px; font-weight:950; color:var(--text); line-height:1; }
.db-card-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
@media(max-width:900px){
    .db-next-grid { grid-template-columns:repeat(2,1fr); }
    .db-work-grid { grid-template-columns:1fr; }
    .db-cand-grid { grid-template-columns:repeat(3,1fr); }
    .db-type-grid { grid-template-columns:repeat(3,1fr); }
}
@media(max-width:600px){
    .db-next-grid { grid-template-columns:1fr; }
    .db-cand-grid { grid-template-columns:repeat(2,1fr); }
    .db-type-grid { grid-template-columns:repeat(2,1fr); }
}
</style>

@php
    $rankLabels = ['S' => 'Sランク', 'A' => 'Aランク', 'B' => 'Bランク', 'C' => 'Cランク', 'D' => 'Dランク'];
    $typeLabels = [
        'renewal_candidate'        => 'リニューアル',
        'cms_conversion_candidate' => 'CMS化',
        'maintenance_candidate'    => '保守',
        'new_site_candidate'       => '新規サイト',
        'reject'                   => '対象外',
        'unclassified'             => '未分類',
    ];
@endphp

<div class="db-topbar">
    <div>
        <div class="db-kicker">TRUSTEPS CMS Lab</div>
        <h1 class="db-title">ダッシュボード</h1>
        <p class="db-sub">データ投入・HP解析・5軸スコアリングの進捗</p>
    </div>
    <div class="db-btn-row">
        <a class="button light small" href="{{ route('source-records.index') }}">HP未確認リスト</a>
        <a class="button light small" href="{{ route('companies.index') }}">企業マスタ</a>
        <a class="button light small" href="{{ route('system.reset-mvp-data.index') }}">MVPリセット</a>
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
            <span class="db-step db-step-gray">2 · HP解析未実施</span>
            <div class="db-next-num">{{ number_format($summary['unanalyzed']) }}</div>
            <div class="db-next-desc">HPあり・未解析のcompany</div>
            <div class="db-next-action">
                <a class="button light small" href="{{ route('companies.index', ['hp_state' => 'unanalyzed']) }}">見る →</a>
            </div>
        </div>
        <div class="db-next-card">
            <span class="db-step db-step-green">3 · S/Aランク</span>
            <div class="db-next-num">{{ number_format($summary['top_rank']) }}</div>
            <div class="db-next-desc">5軸スコア最優先候補</div>
            <div class="db-next-action">
                <a class="button small" href="{{ route('companies.candidates', ['preset' => 'rank_a']) }}">確認する →</a>
            </div>
        </div>
        <div class="db-next-card">
            <span class="db-step db-step-amber">4 · 手動候補</span>
            <div class="db-next-num">{{ number_format($summary['manual']) }}</div>
            <div class="db-next-desc">手動でフラグした候補</div>
            <div class="db-next-action">
                <a class="button light small" href="{{ route('companies.candidates', ['preset' => 'manual']) }}">見る →</a>
            </div>
        </div>
        <div class="db-next-card">
            <span class="db-step db-step-green">5 · Aランク</span>
            <div class="db-next-num">{{ number_format($summary['ranks']['A']) }}</div>
            <div class="db-next-desc">5軸スコア優先候補</div>
            <div class="db-next-action">
                <a class="button small" href="{{ route('companies.candidates', ['sort' => 'v2_rank', 'direction' => 'asc']) }}">確認する →</a>
            </div>
        </div>
        <div class="db-next-card">
            <span class="db-step db-step-blue">6 · Bランク</span>
            <div class="db-next-num">{{ number_format($summary['ranks']['B']) }}</div>
            <div class="db-next-desc">5軸スコア準優先候補</div>
            <div class="db-next-action">
                <a class="button small light" href="{{ route('companies.candidates', ['preset' => 'rank_b']) }}">確認する →</a>
            </div>
        </div>
        @if ($summary['rank_a_low_conf'] > 0)
        <div class="db-next-card">
            <span class="db-step db-step-amber">7 · 目視確認推奨</span>
            <div class="db-next-num">{{ number_format($summary['rank_a_low_conf']) }}</div>
            <div class="db-next-desc">Aランク・信頼度70%未満</div>
            <div class="db-next-action">
                <a class="button light small" href="{{ route('companies.candidates', ['sort' => 'v2_rank', 'direction' => 'asc']) }}">確認する →</a>
            </div>
        </div>
        @endif
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
                <div class="db-work-title">次のHP未確認リスト</div>
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

        {{-- HP解析待ち --}}
        <div class="db-work-card">
            <div class="db-work-head">
                <div class="db-work-title">HP解析待ち</div>
                <span class="db-step db-step-gray" style="margin:0">未解析</span>
            </div>
            <div class="db-work-desc">HPはあるが未解析のcompany。解析でスコアが付く。</div>
            @forelse ($workBoard['hp_analysis_queue'] as $company)
                @php
                    $region = trim(
                        ($company->municipality?->prefecture?->name ?? $company->pref ?? '')
                        . ' '
                        . ($company->municipality?->name ?? $company->city ?? '')
                    );
                @endphp
                <div class="db-item">
                    <div class="db-item-name">#{{ $company->id }} {{ $company->display_name ?? $company->legal_name ?? '名称未設定' }}</div>
                    <div class="db-item-sub">{{ $company->industry?->name ?? '業種未設定' }} · {{ $region !== '' ? $region : '地域未設定' }}</div>
                    <div class="db-item-btn">
                        <a class="button light small" href="{{ route('companies.show', $company) }}">HP解析する</a>
                    </div>
                </div>
            @empty
                <div class="db-item" style="text-align:center;padding:18px 0;color:var(--muted);font-size:13px;">
                    HP解析待ちはありません
                </div>
            @endforelse
        </div>

        {{-- 営業優先候補 --}}
        <div class="db-work-card">
            <div class="db-work-head">
                <div class="db-work-title">営業優先候補</div>
                <span class="db-step db-step-green" style="margin:0">S/A/B順</span>
            </div>
            <div class="db-work-desc">5軸スコアの高ランク順。total_score降順。</div>
            @forelse ($workBoard['priority_queue'] as $s)
                @php $company = $s->company; @endphp
                <div class="db-item">
                    <div class="db-item-name">
                        <span class="db-rank db-rank-{{ $s->rank }}">{{ $s->rank }}</span>
                        #{{ $company->id }} {{ $company->display_name ?? $company->legal_name ?? '名称未設定' }}
                    </div>
                    <div class="db-item-sub">
                        score {{ number_format((float) $s->total_score, 1) }}
                        · {{ $typeLabels[$s->candidate_type] ?? ($s->candidate_type ?: '未分類') }}
                    </div>
                    <div class="db-item-btn">
                        <a class="button small" href="{{ route('companies.show', $company) }}">詳細</a>
                    </div>
                </div>
            @empty
                <div class="db-item" style="text-align:center;padding:18px 0;color:var(--muted);font-size:13px;">
                    優先候補はまだありません
                </div>
            @endforelse
        </div>

    </div>
</section>

{{-- 営業候補の状態 --}}
<section class="card">
    <div class="db-card-head">
        <div class="db-sec-label" style="margin:0">営業候補の状態（5軸スコア）</div>
        <div class="db-btn-row">
            <a class="button small" href="{{ route('companies.candidates', ['preset' => 'rank_a']) }}">S/Aランク →</a>
            <a class="button light small" href="{{ route('companies.candidates') }}">候補一覧</a>
        </div>
    </div>

    <div class="db-cand-grid">
        @foreach (['S', 'A', 'B', 'C', 'D'] as $rank)
        <div class="db-cand-stat">
            <div class="db-cand-label">{{ $rankLabels[$rank] }}</div>
            <div class="db-cand-num">{{ number_format($summary['ranks'][$rank]) }}</div>
        </div>
        @endforeach
    </div>

    <div class="db-type-grid">
        @foreach ($typeLabels as $key => $label)
        <div class="db-type-stat">
            <div class="db-type-label">{{ $label }}</div>
            <div class="db-type-num">{{ number_format($summary['types'][$key]) }}</div>
        </div>
        @endforeach
    </div>
</section>

</main>
@endsection
