@extends('layouts.app', ['title' => 'company詳細 | TRUSTEPS CMS Lab'])

@section('content')
<main class="content cs">
@php
    $hpWeakness       = optional($scoresByAxis->get('hp_weakness'))->value;
    $selfUpdateFit    = optional($scoresByAxis->get('self_update_fit'))->value;
    $devDifficulty    = optional($scoresByAxis->get('dev_difficulty'))->value;
    $portalDependence = optional($scoresByAxis->get('portal_dependence'))->value;

    $scoredAxesCount = collect([$hpWeakness, $selfUpdateFit, $devDifficulty, $portalDependence])
        ->filter(fn ($v) => $v !== null)->count();

    $opportunityScore = ($hpWeakness ?? 0) + ($selfUpdateFit ?? 0);
    $riskScore        = ($devDifficulty ?? 0) + ($portalDependence ?? 0);
    $totalScore       = $opportunityScore + $riskScore;

    if ($scoredAxesCount < 4) {
        $scoreJudgment = '未採点あり'; $scoreJudgmentClass = 'gray';
    } elseif ($totalScore >= 16) {
        $scoreJudgment = '高ポテンシャル'; $scoreJudgmentClass = 'green';
    } elseif ($totalScore >= 12) {
        $scoreJudgment = 'ポテンシャルあり'; $scoreJudgmentClass = 'blue';
    } elseif ($totalScore >= 8) {
        $scoreJudgment = '要確認'; $scoreJudgmentClass = 'amber';
    } else {
        $scoreJudgment = '優先度低'; $scoreJudgmentClass = 'gray';
    }

    $latestFact = null;
    if ($company->primaryDomain) {
        $latestFact = \App\Models\HpFact::query()
            ->join('hp_snapshots', 'hp_facts.hp_snapshot_id', '=', 'hp_snapshots.id')
            ->where('hp_snapshots.domain_id', $company->primaryDomain->id)
            ->whereNotNull('hp_facts.extracted_at')
            ->orderByDesc('hp_facts.extracted_at')
            ->select('hp_facts.*')
            ->first();
    }

    $statusLabels  = ['active' => '更新中', 'partial_active' => '一部更新', 'stale_1y' => '1年以上停止', 'stale_2y' => '2年以上停止', 'unknown' => '不明'];
    $statusClasses = ['active' => 'green', 'partial_active' => 'blue', 'stale_1y' => 'amber', 'stale_2y' => 'red', 'unknown' => 'gray'];
@endphp

<style>
.cs { display:grid; gap:16px; }
.cs-header { background:var(--card); border:1px solid var(--line); border-radius:20px; padding:18px 22px; }
.cs-header-top { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; }
.cs-badges { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:8px; }
.cs-title { margin:0; font-size:24px; font-weight:950; letter-spacing:-.03em; color:var(--text); }
.cs-meta { margin:5px 0 0; font-size:13px; color:var(--muted); display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.cs-header-nav { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:14px; padding-top:14px; border-top:1px solid var(--line); }
.cs-nav-label { font-size:11px; color:var(--muted); font-weight:900; }
.cs-hp-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:8px; }
.cs-hp-item { padding:10px 12px; border-radius:12px; border:1px solid var(--line); background:#f8fafc; }
.cs-hp-k { font-size:10px; color:var(--muted); font-weight:900; margin-bottom:5px; }
.cs-hp-v { font-size:13px; font-weight:700; color:var(--text); }
.cs-score-row { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
.cs-score-card { border:1px solid var(--line); border-radius:14px; padding:12px; background:var(--card); }
.cs-score-card.opp { border-color:#bbf7d0; background:#f0fdf4; }
.cs-score-card-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; }
.cs-axis-key { font-size:10px; color:var(--muted); font-family:monospace; }
.cs-axis-label { font-size:13px; font-weight:950; letter-spacing:-.01em; margin-top:1px; }
.cs-score-val { font-size:28px; font-weight:950; letter-spacing:-.04em; line-height:1; margin:8px 0 4px; }
.cs-score-sub { font-size:10px; color:var(--muted); }
.cs-suggestion-bar { padding:6px 10px; border-radius:8px; background:#eff6ff; border:1px solid #bfdbfe; margin-top:8px; font-size:11px; color:#1d4ed8; font-weight:900; }
.cs-current-bar { margin-top:6px; padding:6px 10px; border-radius:8px; background:#f8fafc; border:1px solid var(--line); font-size:11px; color:var(--muted); }
.cs-kv-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:8px; }
.cs-kv { padding:12px 14px; border-radius:12px; border:1px solid #e8eef6; background:#f8fafc; }
.cs-kv-k { font-size:10px; color:var(--muted); font-weight:900; margin-bottom:5px; }
.cs-kv-v { font-size:13px; font-weight:700; word-break:break-word; }
@media(max-width:800px){ .cs-score-row { grid-template-columns:repeat(2,1fr); } }
@media(max-width:500px){ .cs-score-row { grid-template-columns:1fr; } }
</style>

{{-- ヘッダー（採点ナビ統合） --}}
<div class="cs-header">
    <div class="cs-header-top">
        <div>
            <div class="cs-badges">
                <span class="badge blue">Company #{{ $company->id }}</span>
                <span class="badge gray">{{ $company->status }}</span>
                <span class="badge {{ $company->is_killed ? 'red' : 'green' }}">{{ $company->is_killed ? 'killed' : 'active' }}</span>
                <span class="badge {{ $scoreJudgmentClass }}">{{ $scoreJudgment }}</span>
                <span class="badge {{ $isCurrentScoringQueueTarget ? 'amber' : 'green' }}">{{ $isCurrentScoringQueueTarget ? '採点待ち' : '4軸採点済み' }}</span>
                @if ($company->is_manual_candidate)
                    <span class="badge green">手動候補</span>
                @endif
            </div>
            <h1 class="cs-title">{{ $company->display_name }}</h1>
            <div class="cs-meta">
                <span>{{ $company->industry?->name ?? '業種未設定' }}</span>
                <span>·</span>
                <span>{{ $company->municipality?->prefecture?->name ?? $company->pref ?? '-' }} / {{ $company->municipality?->name ?? $company->city ?? '-' }}</span>
                @if ($company->primaryDomain?->normalized_domain)
                    <span>·</span>
                    <a href="{{ $company->primaryDomain->url }}" target="_blank" rel="noopener" style="font-family:monospace;color:var(--primary);">{{ $company->primaryDomain->normalized_domain }}</a>
                @endif
            </div>
        </div>
        <div class="actions">
            <a class="button light small" href="{{ route('companies.index') }}">一覧</a>
            @if ($company->status !== 'merged')
                <a class="button light small" href="{{ route('companies.edit', $company) }}">編集</a>
                <a class="button light small" href="{{ route('companies.merge-form', $company) }}">統合</a>
            @else
                <form method="POST" action="{{ route('companies.undo-merge', $company) }}" onsubmit="return confirm('統合をUndoする？');">
                    @csrf
                    <button class="button danger small" type="submit">統合Undo</button>
                </form>
            @endif
        </div>
    </div>

    {{-- 採点ナビ --}}
    <div class="cs-header-nav">
        <span class="cs-nav-label">採点キュー {{ $scoringQueueCount }}件</span>
        @if ($previousScoringCompany)
            <a class="button light small" href="{{ route('companies.show', $previousScoringCompany) }}">← #{{ $previousScoringCompany->id }}</a>
        @endif
        @if ($nextScoringCompany)
            <a class="button small" href="{{ route('companies.show', $nextScoringCompany) }}">#{{ $nextScoringCompany->id }} →</a>
        @endif
        @if ($company->primaryDomain)
            <form method="POST" action="{{ route('companies.analyze', $company) }}" style="margin-left:auto;">
                @csrf
                <button class="button small dark" type="submit">HP解析 → スコア自動保存</button>
            </form>
        @endif
    </div>
</div>

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

@if ($company->status === 'merged')
    <div class="error">
        このcompanyは統合済み。統合先：
        @if ($company->mergedInto)
            <a href="{{ route('companies.show', $company->mergedInto) }}">#{{ $company->mergedInto->id }} {{ $company->mergedInto->display_name }}</a>
        @else
            不明
        @endif
    </div>
@endif

{{-- HP解析結果 --}}
<section class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
        <div class="section-label">HP解析（Layer 2）</div>
        @if ($latestFact)
            <span style="font-size:11px; color:var(--muted);">{{ optional($latestFact->extracted_at)->format('Y-m-d H:i') }}</span>
        @endif
    </div>
    @if ($latestFact && ($latestFact->hp_js_rendering_required ?? false))
        <div style="padding:12px 16px;background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;margin-bottom:12px;">
            <div style="font-size:13px;font-weight:700;color:#92400e;">JSサイトのため自動解析不可。目視で確認してください。</div>
            <div style="font-size:11px;color:#b45309;margin-top:4px;">JavaScriptで描画されているためHTMLを取得できませんでした。URLが正しければ手動で確認・入力してください。</div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <form method="POST" action="{{ route('companies.set-primary-url', $company) }}" style="display:flex;gap:6px;align-items:center;flex:1;min-width:260px;">
                @csrf
                <input type="url" name="primary_url" placeholder="https://example.com" value="{{ $company->primaryDomain?->url }}" style="flex:1;padding:6px 10px;border:1px solid var(--line);border-radius:8px;font-size:13px;" required>
                <button class="button small" type="submit">公式URLを手動設定</button>
            </form>
            <form method="POST" action="{{ route('companies.kill-flags.store', $company) }}">
                @csrf
                <input type="hidden" name="flag" value="no_official_site">
                <input type="hidden" name="note" value="JSサイトのため自動解析不可。公式HPなし判定。">
                <button class="button small light" type="submit" style="color:#ef4444;border-color:#fca5a5;">HPなし → kill_flag追加（no_official_site）</button>
            </form>
        </div>
    @elseif ($latestFact)
        <div class="cs-hp-grid">
            <div class="cs-hp-item">
                <div class="cs-hp-k">改善余地スコア</div>
                <div class="cs-hp-v" style="font-size:20px;font-weight:950;">{{ $latestFact->hp_improvement_score ?? '—' }}<span style="font-size:12px;font-weight:400;"> /5</span></div>
            </div>
            <div class="cs-hp-item">
                <div class="cs-hp-k">SSL</div>
                <div class="cs-hp-v"><span class="badge {{ $latestFact->ssl_enabled ? 'green' : 'red' }}">{{ $latestFact->ssl_enabled ? 'あり' : 'なし' }}</span></div>
            </div>
            <div class="cs-hp-item">
                <div class="cs-hp-k">スマホ対応</div>
                <div class="cs-hp-v"><span class="badge {{ $latestFact->mobile_friendly ? 'green' : 'red' }}">{{ $latestFact->mobile_friendly ? '対応' : '非対応' }}</span></div>
            </div>
            <div class="cs-hp-item">
                <div class="cs-hp-k">更新状況</div>
                <div class="cs-hp-v">
                    <span class="badge {{ $statusClasses[$latestFact->update_status] ?? 'gray' }}">{{ $statusLabels[$latestFact->update_status] ?? '不明' }}</span>
                    @if ($latestFact->hp_update_staleness_days !== null)
                        <span style="font-size:10px;color:var(--muted);display:block;margin-top:2px;">約{{ $latestFact->hp_update_staleness_days }}日前</span>
                    @endif
                </div>
            </div>
            <div class="cs-hp-item">
                <div class="cs-hp-k">CMS</div>
                <div class="cs-hp-v">{{ $latestFact->cms_type ?? '不明' }}</div>
            </div>
            <div class="cs-hp-item">
                <div class="cs-hp-k">お知らせ</div>
                <div class="cs-hp-v"><span class="badge {{ $latestFact->hp_has_news ? 'green' : 'gray' }}">{{ $latestFact->hp_has_news ? 'あり' : 'なし' }}</span></div>
            </div>
            <div class="cs-hp-item">
                <div class="cs-hp-k">問い合わせ</div>
                <div class="cs-hp-v">{{ $latestFact->contact_method_type ?? '不明' }}</div>
            </div>
            <div class="cs-hp-item" style="grid-column:span 2;">
                <div class="cs-hp-k">営業入り口</div>
                <div class="cs-hp-v" style="display:flex;flex-direction:column;gap:4px;">
                    @if ($latestFact->hp_contact_email)
                        <a href="mailto:{{ $latestFact->hp_contact_email }}" style="color:var(--primary);font-size:12px;word-break:break-all;">{{ $latestFact->hp_contact_email }}</a>
                    @endif
                    @if ($latestFact->hp_contact_form_url)
                        <a href="{{ $latestFact->hp_contact_form_url }}" target="_blank" rel="noopener" style="color:var(--primary);font-size:12px;word-break:break-all;">フォーム: {{ $latestFact->hp_contact_form_url }}</a>
                    @endif
                    @if ($latestFact->hp_contact_phone)
                        <span style="font-size:12px;">{{ $latestFact->hp_contact_phone }}</span>
                    @endif
                    @if (!$latestFact->hp_contact_email && !$latestFact->hp_contact_form_url && !$latestFact->hp_contact_phone)
                        <span style="font-size:12px;color:var(--muted);">営業入り口なし</span>
                    @endif
                </div>
            </div>
            <div class="cs-hp-item">
                <div class="cs-hp-k">ポータル依存</div>
                <div class="cs-hp-v">
                    @php $pLevel = $latestFact->portal_dependency_level ?? 'none'; @endphp
                    <span class="badge {{ in_array($pLevel, ['medium','high']) ? 'red' : 'green' }}">{{ $pLevel }}</span>
                    @if ($latestFact->hp_has_tabelog)   <span style="font-size:10px;color:var(--muted);display:block;">食べログ</span> @endif
                    @if ($latestFact->hp_has_hotpepper) <span style="font-size:10px;color:var(--muted);display:block;">ホットペッパー</span> @endif
                    @if ($latestFact->hp_has_jalan)     <span style="font-size:10px;color:var(--muted);display:block;">じゃらん/楽天</span> @endif
                    @if ($latestFact->hp_has_suumo)     <span style="font-size:10px;color:var(--muted);display:block;">SUUMO</span> @endif
                </div>
            </div>
            <div class="cs-hp-item">
                <div class="cs-hp-k">画像 / 文字</div>
                <div class="cs-hp-v">{{ $latestFact->hp_image_count ?? '—' }}枚 / {{ $latestFact->hp_word_count ? number_format($latestFact->hp_word_count) : '—' }}字</div>
            </div>
        </div>
        @if ($latestFact->hp_title)
            <div style="font-size:11px;color:var(--muted);margin-top:10px;padding-top:10px;border-top:1px solid var(--line);">
                タイトル：{{ $latestFact->hp_title }}
            </div>
        @endif
    @else
        <div style="font-size:13px;color:var(--muted);">未解析。ヘッダーの「HP解析 → スコア自動保存」ボタンで実行するとスコアが自動設定されます。</div>
    @endif
</section>

{{-- 営業判断（手動候補） --}}
<section class="card">
    <div class="section-label" style="margin-bottom:12px;">営業判断</div>

    @if ($company->is_manual_candidate)
        <div style="display:flex; flex-direction:column; gap:10px;">
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span class="badge green" style="font-size:13px; padding:6px 14px;">手動候補</span>
                <span style="font-size:12px; color:var(--muted);">追加日: {{ optional($company->manual_candidate_at)->format('Y-m-d H:i') ?? '—' }}</span>
                <span style="font-size:12px; color:var(--muted);">追加者: {{ $company->manual_candidate_by ?? '—' }}</span>
            </div>
            @if ($company->manual_candidate_reason)
                <div style="font-size:13px; padding:10px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; color:#15803d; white-space:pre-wrap;">{{ $company->manual_candidate_reason }}</div>
            @endif
            <div>
                <form method="POST" action="{{ route('companies.manual-candidate.unset', $company) }}" onsubmit="return confirm('手動候補を解除しますか？');">
                    @csrf
                    @method('DELETE')
                    <button class="button danger small" type="submit">候補から外す</button>
                </form>
            </div>
        </div>
    @else
        <details id="manual-candidate-details">
            <summary style="cursor:pointer; list-style:none; display:inline-block;">
                <button class="button light small" type="button" onclick="document.getElementById('manual-candidate-details').setAttribute('open','');" style="pointer-events:none;">手動で営業候補に追加</button>
            </summary>
            <form method="POST" action="{{ route('companies.manual-candidate.set', $company) }}" style="margin-top:12px; display:flex; flex-direction:column; gap:8px; max-width:600px;">
                @csrf
                <div class="field" style="margin-bottom:0;">
                    <label for="manual_candidate_reason">追加理由（任意）</label>
                    <textarea id="manual_candidate_reason" name="manual_candidate_reason" rows="3" placeholder="例: 担当者と名刺交換済み、HP老朽化が顕著で提案しやすい等"></textarea>
                </div>
                <div>
                    <button class="button small" type="submit">候補に追加</button>
                </div>
            </form>
        </details>
    @endif
</section>

{{-- 5軸スコア (scoring_v1.0) --}}
<section class="card">
    @php
        $v2Axes = [
            'opportunity_score'  => 'HP改善機会',
            'impact_score'       => '案件インパクト',
            'feasibility_score'  => '実行容易性',
            'reachability_score' => '営業到達性',
            'recurring_score'    => '継続性',
        ];
        $v2TypeLabels = [
            'renewal_candidate'        => 'HPリニューアル候補',
            'cms_conversion_candidate' => 'CMS化候補',
            'maintenance_candidate'    => '保守・更新候補',
            'new_site_candidate'       => '新規制作候補',
            'reject'                   => '優先度低',
            'unclassified'             => '未分類',
        ];
        $v2RankColors = ['A' => 'green', 'B' => 'blue', 'C' => 'amber', 'D' => 'red'];
        $v2Rank       = $scoreSummary?->rank;
        $v2RankColor  = $v2RankColors[$v2Rank] ?? 'gray';
        $v2TypeKey    = $scoreSummary?->candidate_type;
        $v2TypeLabel  = $v2TypeLabels[$v2TypeKey] ?? ($v2TypeKey ?? '—');
        $v2Conf       = $scoreSummary?->confidence;
        $v2Total      = $scoreSummary?->total_score;
        $v2Caps       = $scoreSummary?->caps_applied_json ?? [];
    @endphp

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:14px;">
        <div class="section-label">5軸スコア（scoring_v1.0）</div>
        @if ($scoreSummary)
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span style="font-size:13px;font-weight:600;">総合 {{ number_format((float)$v2Total, 1) }} / 5.0</span>
                <span class="badge {{ $v2RankColor }}">ランク {{ $v2Rank }}</span>
                <span class="badge blue" style="font-size:11px;">{{ $v2TypeLabel }}</span>
                @if ($v2Conf !== null)
                    <span style="font-size:11px;color:var(--muted);">信頼度 {{ (int)round($v2Conf * 100) }}%</span>
                    @if ($v2Conf < 0.70)
                        <span class="badge amber" style="font-size:11px;">目視確認推奨</span>
                    @endif
                @endif
            </div>
        @else
            <span class="badge gray">未計算（scores:recalculate を実行してください）</span>
        @endif
    </div>

    <style>
        .v2-axis-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:14px;align-items:start;}
        .v2-axis-card{border:1px solid var(--line);border-radius:14px;padding:12px;background:#fff;display:flex;flex-direction:column;}
        .v2-axis-name{font-weight:700;font-size:13px;line-height:1.3;}
        .v2-axis-key{display:block;font-size:9px;color:var(--muted);margin-top:2px;}
        .v2-axis-score{font-size:20px;font-weight:800;color:#166534;line-height:1;margin-top:6px;}
        .v2-axis-score span{font-size:11px;font-weight:400;color:var(--muted);}
        .v2-bar{height:5px;border-radius:4px;background:#e5e7eb;margin:7px 0 9px;overflow:hidden;}
        .v2-bar-fill{height:100%;background:linear-gradient(90deg,#22c55e,#16a34a);}
        .v2-factors{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:7px;}
        .v2-factors .badge{font-size:10px;}
        .v2-factors .badge b{font-variant-numeric:tabular-nums;}
        .v2-narrative{font-size:11px;line-height:1.65;color:#374151;background:#f8fafc;border-radius:8px;padding:8px 10px;margin-bottom:8px;}
        .v2-acc{margin-top:auto;}
        .v2-acc summary{cursor:pointer;font-size:11px;font-weight:600;color:var(--muted);padding:4px 0;list-style:none;}
        .v2-acc summary::-webkit-details-marker{display:none;}
        .v2-sub-table{width:100%;border-collapse:collapse;font-size:10px;margin:6px 0 2px;}
        .v2-sub-table th,.v2-sub-table td{text-align:left;padding:3px 4px;border-bottom:1px solid #f0f0f0;}
        .v2-sub-table th{color:var(--muted);font-weight:600;font-size:9px;}
        .v2-sub-table td:nth-child(2),.v2-sub-table td:nth-child(3),.v2-sub-table td:nth-child(4){text-align:right;font-variant-numeric:tabular-nums;}
        .v2-sub-table tr.v2-neutral td{color:#9ca3af;background:#fafafa;}
        .v2-sub-key{display:block;font-size:8px;color:#b0b0b0;}
        .v2-sub-table tfoot td{border-top:2px solid #e5e7eb;font-size:10px;border-bottom:none;padding-top:5px;}
    </style>

    <div class="v2-axis-grid">
        @foreach ($v2Axes as $axisKey => $axisLabel)
            @php
                $axisRow  = $scoresV2->get($axisKey);
                $rjson    = $axisRow?->reason_json ?? [];
                $detail   = $rjson['axis_detail'] ?? null;
                $rawScore = $rjson['score_raw'] ?? ($axisRow?->value);
                $scoreNum = $rawScore !== null ? (float) $rawScore : null;
                $pct      = $scoreNum !== null ? max(0, min(100, $scoreNum / 5 * 100)) : 0;
            @endphp
            <div class="v2-axis-card">
                <div class="v2-axis-name">{{ $axisLabel }}<span class="v2-axis-key">{{ $axisKey }}</span></div>
                <div class="v2-axis-score">{{ $scoreNum !== null ? number_format($scoreNum, 1) : '—' }}<span>/5</span></div>
                <div class="v2-bar"><div class="v2-bar-fill" style="width:{{ $pct }}%"></div></div>

                @if ($detail)
                    @if (!empty($detail['positive_factors']))
                        <div class="v2-factors">
                            @foreach ($detail['positive_factors'] as $f)
                                <span class="badge green">✓ {{ $f['label'] }} <b>{{ sprintf('%+.2f', $f['contribution']) }}</b></span>
                            @endforeach
                        </div>
                    @endif
                    @if (!empty($detail['negative_factors']))
                        <div class="v2-factors">
                            @foreach ($detail['negative_factors'] as $f)
                                <span class="badge amber">✗ {{ $f['label'] }} <b>{{ sprintf('%+.2f', $f['contribution']) }}</b></span>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($detail['narrative']))
                        <div class="v2-narrative">{{ $detail['narrative'] }}</div>
                    @endif

                    @if (!empty($detail['sub_scores']))
                        <details class="v2-acc">
                            <summary>サブスコア内訳 ▼</summary>
                            <table class="v2-sub-table">
                                <thead>
                                    <tr><th>項目</th><th>score</th><th>重み</th><th>寄与</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($detail['sub_scores'] as $sub)
                                        <tr class="{{ !empty($sub['is_neutral']) ? 'v2-neutral' : '' }}">
                                            <td>
                                                {{ $sub['label'] ?? $sub['key'] }}
                                                <span class="v2-sub-key">{{ $sub['key'] }}@if (!empty($sub['is_neutral'])) · 未実装/中立 @endif</span>
                                            </td>
                                            <td>{{ number_format((float) $sub['score'], 1) }}</td>
                                            <td>{{ number_format((float) $sub['weight'], 2) }}</td>
                                            <td>{{ number_format((float) $sub['contribution'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td>加重合計<span class="v2-sub-key">ゲート/キャップ前</span></td>
                                        <td colspan="3" style="text-align:right;">
                                            <b>{{ number_format((float) ($detail['weighted_total'] ?? 0), 2) }}</b>
                                        </td>
                                    </tr>
                                    @if (!empty($detail['flag_cap_applied']) || !empty($detail['gate_applied']))
                                        <tr>
                                            <td colspan="4" style="border:none;padding-top:4px;">
                                                @if (!empty($detail['flag_cap_applied']))<span class="badge amber" style="font-size:9px;">flagキャップ適用</span>@endif
                                                @if (!empty($detail['gate_applied']))<span class="badge amber" style="font-size:9px;">ゲート発火軸</span>@endif
                                            </td>
                                        </tr>
                                    @endif
                                </tfoot>
                            </table>
                        </details>
                    @endif
                @else
                    <div class="v2-narrative">詳細データなし（scores:recalculate の再実行が必要です）。</div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- 採点根拠 --}}
    @if ($reasonJson)
        <div style="border-top:1px solid var(--border); margin-top:14px; padding-top:14px;">
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">採点根拠</div>

            @if (!empty($reasonJson['positive']))
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                    @foreach ($reasonJson['positive'] as $item)
                        <span class="badge green" style="font-size:11px;">✓ {{ $item }}</span>
                    @endforeach
                </div>
            @endif

            @if (!empty($reasonJson['negative']))
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                    @foreach ($reasonJson['negative'] as $item)
                        <span class="badge amber" style="font-size:11px;">✗ {{ $item }}</span>
                    @endforeach
                </div>
            @endif

            @if (!empty($v2Caps))
                <div style="font-size:11px;color:var(--muted);margin-bottom:8px;">
                    <span style="font-weight:600;">スコア上限補正あり：</span>
                    @foreach ($v2Caps as $cap)
                        @php
                            $capLabel = isset($cap['flag'])
                                ? ($cap['axis'] ?? '?') . '≤' . $cap['cap'] . ' (' . $cap['flag'] . ')'
                                : ($cap['gate'] ?? '?') . ' → 上限' . $cap['cap'];
                        @endphp
                        <span class="badge amber" style="font-size:10px;">{{ $capLabel }}</span>
                    @endforeach
                </div>
            @endif

            @if (!empty($reasonJson['evidence']))
                @php $ev = $reasonJson['evidence']; @endphp
                <div style="font-size:10px;color:var(--muted);line-height:1.9;margin-top:6px;padding:8px 10px;background:var(--bg2,#f8fafc);border-radius:8px;">
                    <span style="font-weight:600;">業種:</span>
                    {{ $ev['industry_key'] ?? '—' }}
                    {{ ($ev['has_industry_scores'] ?? false) ? '（業種スコアあり）' : '（業種スコアなし・中央値）' }}
                    &nbsp;|&nbsp;
                    <span style="font-weight:600;">HP入口:</span>
                    {{ ($ev['site_analysis']['has_hp'] ?? false) ? 'HPあり' : 'HPなし' }}
                    @if ($ev['site_analysis']['has_form'] ?? false) ・フォームあり @endif
                    @if ($ev['site_analysis']['has_email'] ?? false) ・メールあり @endif
                    @if ($ev['site_analysis']['has_phone'] ?? false) ・電話あり @endif
                    <br>
                    @if (!empty($ev['normalized']))
                        <span style="font-weight:600;">主要シグナル:</span>
                        @foreach ($ev['normalized'] as $sigKey => $sigVal)
                            <span style="background:#e5e7eb;border-radius:3px;padding:1px 5px;display:inline-block;margin:1px 2px;">{{ $sigKey }}&nbsp;{{ number_format((float)$sigVal, 1) }}</span>
                        @endforeach
                    @endif
                </div>
            @endif
        </div>
    @endif
</section>

{{-- kill_flags --}}
<section class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
        <div class="section-label">kill_flags</div>
        <span class="badge {{ $company->is_killed ? 'red' : 'green' }}">is_killed={{ $company->is_killed ? 'true' : 'false' }}</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>flag</th><th>note</th><th>source</th><th>flagged_at</th><th></th></tr></thead>
            <tbody>
            @forelse ($company->killFlags as $killFlag)
                <tr>
                    <td><strong>{{ $killFlag->flag }}</strong></td>
                    <td>{{ $killFlag->note ?? '-' }}</td>
                    <td>{{ $killFlag->source ?? '-' }}</td>
                    <td>{{ optional($killFlag->flagged_at)->format('Y-m-d H:i') ?? '-' }}</td>
                    <td>
                        <form method="POST" action="{{ route('companies.kill-flags.destroy', [$company, $killFlag]) }}" onsubmit="return confirm('このkill_flagを解除する？');">
                            @csrf
                            @method('DELETE')
                            <button class="button small danger" type="submit">解除</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">kill_flagなし</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <form method="POST" action="{{ route('companies.kill-flags.store', $company) }}" style="margin-top:12px;">
        @csrf
        <div class="grid">
            <div class="field" style="margin-bottom:0;">
                <label for="flag">kill_flag追加</label>
                <select id="flag" name="flag" required>
                    <option value="">選択</option>
                    <option value="no_official_site">no_official_site：公式HPなし</option>
                    <option value="defunct">defunct：活動停止・閉業</option>
                    <option value="chain_no_edit_rights">chain_no_edit_rights：ローカル編集権限なし</option>
                    <option value="out_of_scope_size">out_of_scope_size：対象外規模</option>
                    <option value="compliance_risk">compliance_risk：コンプライアンス・対象外属性</option>
                </select>
            </div>
            <div class="field" style="margin-bottom:0;">
                <label for="kill_note">note</label>
                <input id="kill_note" name="note" type="text" placeholder="何を見て判断したか">
            </div>
            <div class="field" style="margin-bottom:0; align-self:end;">
                <button class="button danger" type="submit">追加</button>
            </div>
        </div>
    </form>
</section>

{{-- 営業管理 --}}
@php
    use App\Http\Controllers\OutreachController;
    $phasesMeta = OutreachController::PHASES;
    $contactMethods = OutreachController::CONTACT_METHODS;
    $outreachHistory = $company->outreachContacts ?? collect();
    $currentOutreach = $outreachHistory->first();
    $currentPhase = $currentOutreach?->phase ?? null;
    $phaseBadgeColors = ['list'=>'gray','attacked'=>'blue','negotiating'=>'amber','contracted'=>'green','rejected'=>'red','hold'=>'gray'];
@endphp
<section class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:14px;">
        <div class="section-label">営業管理</div>
        @if ($currentPhase)
            <span class="badge {{ $phaseBadgeColors[$currentPhase] ?? 'gray' }}">{{ $phasesMeta[$currentPhase]['label'] ?? $currentPhase }}</span>
        @else
            <span class="badge gray">未登録</span>
        @endif
    </div>

    {{-- フェーズ変更ボタン --}}
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;">
        @foreach ($phasesMeta as $phaseKey => $phaseMeta)
            <form method="POST" action="{{ route('outreach.phase', $company) }}">
                @csrf
                <input type="hidden" name="phase" value="{{ $phaseKey }}">
                <button class="button small {{ $currentPhase === $phaseKey ? '' : 'light' }}" type="submit"
                    style="{{ $currentPhase === $phaseKey ? '' : 'opacity:.7;' }}">
                    {{ $phaseMeta['label'] }}
                </button>
            </form>
        @endforeach
    </div>

    {{-- コンタクト記録フォーム --}}
    <details style="margin-bottom:14px;">
        <summary style="cursor:pointer;font-size:13px;font-weight:700;padding:8px 0;list-style:none;display:flex;align-items:center;gap:6px;">
            <span style="width:18px;height:18px;border-radius:4px;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;flex:0 0 auto;">+</span>
            コンタクト記録を追加
        </summary>
        <form method="POST" action="{{ route('outreach.contact.store', $company) }}" style="margin-top:10px;padding:14px;background:#f8fafc;border:1px solid var(--line);border-radius:10px;">
            @csrf
            <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:10px;">
                <div class="field" style="margin-bottom:0;">
                    <label for="oc_phase">フェーズ</label>
                    <select id="oc_phase" name="phase" required>
                        @foreach ($phasesMeta as $pk => $pm)
                            <option value="{{ $pk }}" @selected($currentPhase === $pk || (!$currentPhase && $pk === 'list'))>{{ $pm['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="oc_method">コンタクト方法</label>
                    <select id="oc_method" name="contact_method">
                        <option value="">—</option>
                        @foreach ($contactMethods as $mk => $ml)
                            <option value="{{ $mk }}">{{ $ml }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="oc_contacted_at">コンタクト日時</label>
                    <input id="oc_contacted_at" type="datetime-local" name="contacted_at">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="oc_next_action_at">次アクション日</label>
                    <input id="oc_next_action_at" type="date" name="next_action_at">
                </div>
            </div>
            <div class="field" style="margin-bottom:10px;">
                <label for="oc_next_action">次アクション内容</label>
                <input id="oc_next_action" type="text" name="next_action" placeholder="例：メール送付、訪問アポ確認">
            </div>
            <div class="field" style="margin-bottom:10px;">
                <label for="oc_memo">メモ</label>
                <textarea id="oc_memo" name="memo" rows="2" placeholder="商談内容・状況メモ"></textarea>
            </div>
            <button class="button small" type="submit">記録を保存</button>
        </form>
    </details>

    {{-- 営業履歴 --}}
    @if ($outreachHistory->isNotEmpty())
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>日時</th>
                        <th>フェーズ</th>
                        <th>方法</th>
                        <th>次アクション</th>
                        <th>メモ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($outreachHistory as $oc)
                        <tr>
                            <td style="white-space:nowrap;font-size:12px;">{{ optional($oc->contacted_at ?? $oc->created_at)->format('Y-m-d H:i') }}</td>
                            <td><span class="badge {{ $phaseBadgeColors[$oc->phase] ?? 'gray' }}">{{ $phasesMeta[$oc->phase]['label'] ?? $oc->phase }}</span></td>
                            <td style="font-size:12px;">{{ $contactMethods[$oc->contact_method] ?? '—' }}</td>
                            <td style="font-size:12px;">
                                @if ($oc->next_action_at)
                                    <span style="font-size:11px;color:var(--muted);">{{ $oc->next_action_at->format('m/d') }}</span>
                                @endif
                                {{ $oc->next_action ?? '—' }}
                            </td>
                            <td style="font-size:12px;max-width:240px;">{{ $oc->memo ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('outreach.contact.destroy', [$company, $oc]) }}" onsubmit="return confirm('削除する？');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button small danger" type="submit">削除</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div style="font-size:13px;color:var(--muted);">営業記録なし。上のフェーズボタンまたはフォームから記録できます。</div>
    @endif
</section>

{{-- company基本情報 --}}
<section class="card">
    <p class="section-label" style="margin-bottom:12px;">company基本情報</p>
    <div class="cs-kv-grid">
        <div class="cs-kv"><div class="cs-kv-k">status</div><div class="cs-kv-v"><span class="badge gray">{{ $company->status }}</span></div></div>
        <div class="cs-kv"><div class="cs-kv-k">display_name</div><div class="cs-kv-v">{{ $company->display_name }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">legal_name</div><div class="cs-kv-v">{{ $company->legal_name ?? '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">industry</div><div class="cs-kv-v">{{ $company->industry?->name ?? '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">municipality</div><div class="cs-kv-v">{{ $company->municipality?->prefecture?->name ?? $company->pref ?? '-' }} / {{ $company->municipality?->name ?? $company->city ?? '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">corporate_number</div><div class="cs-kv-v">{{ $company->corporate_number ?? '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">primary_domain</div><div class="cs-kv-v" style="overflow-wrap:anywhere;">{{ $company->primaryDomain?->url ?? '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">merged_into</div><div class="cs-kv-v">{{ $company->mergedInto ? '#'.$company->mergedInto->id.' '.$company->mergedInto->display_name : '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">created_at</div><div class="cs-kv-v">{{ optional($company->created_at)->format('Y-m-d H:i') ?? '-' }}</div></div>
    </div>
    @if ($company->mergedChildren->count())
        <div style="margin-top:14px;">
            <p class="section-label" style="margin-bottom:8px;">このcompanyに統合されたcompany</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>display_name</th><th>status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($company->mergedChildren as $child)
                        <tr>
                            <td>{{ $child->id }}</td>
                            <td>{{ $child->display_name }}</td>
                            <td>{{ $child->status }}</td>
                            <td><a class="button small light" href="{{ route('companies.show', $child) }}">詳細</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>

{{-- domains --}}
<section class="card">
    <p class="section-label" style="margin-bottom:12px;">domains</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>url</th><th>normalized_domain</th><th>role</th><th>primary</th></tr></thead>
            <tbody>
            @forelse ($company->domains as $domain)
                <tr>
                    <td>{{ $domain->id }}</td>
                    <td style="overflow-wrap:anywhere;">{{ $domain->url }}</td>
                    <td>{{ $domain->normalized_domain ?? '-' }}</td>
                    <td>{{ $domain->role }}</td>
                    <td>{{ $domain->is_primary ? 'true' : 'false' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">domainなし</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

{{-- source links --}}
<section class="card">
    <p class="section-label" style="margin-bottom:12px;">source links</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>source_record_id</th><th>match_type</th><th>source_type</th><th>domain</th><th></th></tr></thead>
            <tbody>
            @forelse ($company->sourceLinks as $link)
                <tr>
                    <td>{{ $link->source_record_id }}</td>
                    <td>{{ $link->match_type }}</td>
                    <td>{{ $link->sourceRecord?->source_type ?? '-' }}</td>
                    <td>{{ $link->sourceRecord?->normalized_domain ?? '-' }}</td>
                    <td>
                        @if ($link->sourceRecord)
                            <a class="button small light" href="{{ route('source-records.show', $link->sourceRecord) }}">sourceを見る</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">source linkなし</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

</main>
@endsection
