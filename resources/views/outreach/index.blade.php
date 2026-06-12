@extends('layouts.app', ['title' => '営業管理 | TRUSTEPS CMS Lab'])

@section('content')
<main class="content">
<style>
.or-kanban { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:14px; align-items:start; }
.or-col { border:1px solid var(--line); border-radius:14px; overflow:hidden; background:var(--card); }
.or-col-head { padding:12px 16px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--line); }
.or-col-label { font-size:12px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; }
.or-col-count { font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px; }
.or-cards { display:grid; gap:0; }
.or-card { padding:12px 16px; border-bottom:1px solid var(--line); transition:background .1s; }
.or-card:last-child { border-bottom:none; }
.or-card:hover { background:#f8fafc; }
.or-card-name { font-size:13px; font-weight:700; color:var(--text); text-decoration:none; }
.or-card-name:hover { color:var(--primary); }
.or-card-sub { font-size:11px; color:var(--muted); margin-top:2px; }
.or-card-meta { display:flex; gap:6px; flex-wrap:wrap; margin-top:6px; align-items:center; }
.or-overdue { color:#b91c1c; font-size:11px; font-weight:700; background:#fee2e2; padding:2px 7px; border-radius:4px; }
.or-next { font-size:11px; color:#166534; font-weight:700; background:#dcfce7; padding:2px 7px; border-radius:4px; }
.or-empty { padding:16px; font-size:12px; color:var(--muted); text-align:center; }
</style>

@php
$phasesMeta = \App\Http\Controllers\OutreachController::PHASES;
$phaseColorMap = [
    'gray'  => ['head' => '#f1f5f9', 'text' => '#475569', 'badge-bg' => '#f1f5f9', 'badge-text' => '#475569'],
    'blue'  => ['head' => '#eff6ff', 'text' => '#1d4ed8', 'badge-bg' => '#dbeafe', 'badge-text' => '#1d4ed8'],
    'amber' => ['head' => '#fffbeb', 'text' => '#92400e', 'badge-bg' => '#fef3c7', 'badge-text' => '#92400e'],
    'green' => ['head' => '#f0fdf4', 'text' => '#166534', 'badge-bg' => '#dcfce7', 'badge-text' => '#15803d'],
    'red'   => ['head' => '#fef2f2', 'text' => '#b91c1c', 'badge-bg' => '#fee2e2', 'badge-text' => '#b91c1c'],
];
$totalCount = $phaseCounts->sum();
@endphp

<div style="margin-bottom:20px;">
    <div class="row">
        <div>
            <p class="page-kicker">Outreach</p>
            <h1 class="page-title">営業管理</h1>
            <p class="page-subtitle">フェーズ別に営業進捗を管理。</p>
        </div>
        <span class="badge gray" style="font-size:13px;padding:6px 12px;">合計 {{ $totalCount }} 社</span>
    </div>
</div>

<div class="or-kanban">
    @foreach ($phasesMeta as $phaseKey => $phaseMeta)
        @php
            $color = $phaseColorMap[$phaseMeta['color']] ?? $phaseColorMap['gray'];
            $companies = $companiesByPhase[$phaseKey] ?? collect();
            $count = $phaseCounts[$phaseKey] ?? 0;
        @endphp
        <div class="or-col">
            <div class="or-col-head" style="background:{{ $color['head'] }};">
                <span class="or-col-label" style="color:{{ $color['text'] }};">{{ $phaseMeta['label'] }}</span>
                <span class="or-col-count" style="background:{{ $color['badge-bg'] }};color:{{ $color['badge-text'] }};">{{ $count }}</span>
            </div>
            <div class="or-cards">
                @forelse ($companies as $company)
                    @php $outreach = $company->latest_outreach; @endphp
                    <div class="or-card">
                        <a class="or-card-name" href="{{ route('companies.show', $company) }}">{{ $company->display_name }}</a>
                        <div class="or-card-sub">{{ $company->industry?->name ?? '-' }} · {{ $company->municipality?->prefecture?->name ?? $company->pref ?? '-' }}</div>
                        <div class="or-card-meta">
                            @if ($company->total_score > 0)
                                <span class="badge green">スコア {{ $company->total_score }}/20</span>
                            @endif
                            @if ($outreach->contacted_at)
                                <span style="font-size:11px;color:var(--muted);">最終: {{ $outreach->contacted_at->format('m/d') }}</span>
                            @endif
                            @if ($company->next_action_overdue && $outreach->next_action_at)
                                <span class="or-overdue">次アクション {{ $outreach->next_action_at->format('m/d') }} 超過</span>
                            @elseif ($outreach->next_action_at)
                                <span class="or-next">次: {{ $outreach->next_action_at->format('m/d') }}</span>
                            @endif
                        </div>
                        @if ($outreach->next_action)
                            <div style="font-size:11px;color:var(--muted);margin-top:4px;">→ {{ Str::limit($outreach->next_action, 40) }}</div>
                        @endif
                    </div>
                @empty
                    <div class="or-empty">なし</div>
                @endforelse
            </div>
        </div>
    @endforeach
</div>

</main>
@endsection
