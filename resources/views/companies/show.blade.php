@extends('layouts.app', ['title' => 'company詳細 | TRUSTEPS CMS Lab'])

@section('content')
<main class="content cs">
@php
    $hpWeakness     = optional($scoresByAxis->get('hp_weakness'))->value;
    $selfUpdateFit  = optional($scoresByAxis->get('self_update_fit'))->value;
    $devDifficulty  = optional($scoresByAxis->get('dev_difficulty'))->value;
    $portalDependence = optional($scoresByAxis->get('portal_dependence'))->value;

    $scoredAxesCount = collect([$hpWeakness, $selfUpdateFit, $devDifficulty, $portalDependence])
        ->filter(fn ($v) => $v !== null)->count();

    $opportunityScore = ($hpWeakness ?? 0) + ($selfUpdateFit ?? 0);
    $riskScore = ($devDifficulty ?? 0) + ($portalDependence ?? 0);

    if ($scoredAxesCount < 4) {
        $scoreJudgment = '未採点あり'; $scoreJudgmentClass = 'gray';
    } elseif ($opportunityScore >= 7 && $riskScore <= 3) {
        $scoreJudgment = '高機会・低リスク'; $scoreJudgmentClass = 'green';
    } elseif ($opportunityScore >= 7 && $riskScore >= 7) {
        $scoreJudgment = '高機会・高リスク'; $scoreJudgmentClass = 'blue';
    } elseif ($opportunityScore <= 3 && $riskScore >= 7) {
        $scoreJudgment = '低機会・高リスク'; $scoreJudgmentClass = 'red';
    } elseif ($opportunityScore <= 3 && $riskScore <= 3) {
        $scoreJudgment = '低機会・低リスク'; $scoreJudgmentClass = 'gray';
    } else {
        $scoreJudgment = '要確認'; $scoreJudgmentClass = 'blue';
    }

    $suggestionPayload = [];
    foreach (($scoreSuggestions ?? []) as $suggestionAxis => $suggestionData) {
        if (is_array($suggestionData) && array_key_exists('value', $suggestionData) && $suggestionData['value'] !== null) {
            $suggestionPayload[] = [
                'axis'       => $suggestionAxis,
                'value'      => (int) $suggestionData['value'],
                'confidence' => (string) ($suggestionData['confidence'] ?? '0.3'),
            ];
        }
    }
@endphp

<style>
.cs { display:grid; gap:18px; }
.cs-topbar { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; }
.cs-kicker { font-size:11px; font-weight:900; color:var(--muted); letter-spacing:.1em; text-transform:uppercase; margin-bottom:6px; display:flex; gap:8px; flex-wrap:wrap; }
.cs-title { margin:0; font-size:26px; font-weight:950; letter-spacing:-.03em; color:var(--text); }
.cs-meta { margin:6px 0 0; font-size:13px; color:var(--muted); display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.cs-sec-label { font-size:10px; font-weight:900; color:var(--muted); letter-spacing:.1em; text-transform:uppercase; margin-bottom:12px; }
.cs-score-summary { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
.cs-score-stat { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:16px; }
.cs-score-stat.opp { background:#f0fdf4; border-color:#bbf7d0; }
.cs-score-stat.risk { background:#fff7ed; border-color:#fed7aa; }
.cs-score-num { font-size:32px; font-weight:950; letter-spacing:-.04em; line-height:1; margin:6px 0 4px; }
.cs-score-sub { font-size:11px; color:var(--muted); }
.cs-nav-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.cs-score-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; }
.cs-score-card { border:1px solid var(--line); border-radius:16px; padding:16px; background:var(--card); }
.cs-score-card.risk-card { background:#fffaf3; border-color:#fed7aa; }
.cs-score-card-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
.cs-axis-key { font-size:11px; color:var(--muted); font-family:monospace; }
.cs-axis-label { font-size:15px; font-weight:950; letter-spacing:-.02em; margin-top:2px; }
.cs-suggestion { padding:10px 12px; border-radius:10px; background:#eff6ff; border:1px solid #bfdbfe; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
.cs-suggestion-label { font-size:12px; color:#1d4ed8; font-weight:900; }
.cs-current { margin-top:10px; padding:10px 12px; border-radius:10px; background:#f8fafc; border:1px solid var(--line); font-size:12px; color:var(--muted); }
.cs-current-val { font-size:20px; font-weight:950; color:var(--text); margin-bottom:2px; }
.cs-kv-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px; }
.cs-kv { padding:14px; border-radius:14px; border:1px solid #e8eef6; background:#f8fafc; }
.cs-kv-k { font-size:11px; color:var(--muted); font-weight:900; margin-bottom:6px; }
.cs-kv-v { font-size:14px; font-weight:700; word-break:break-word; }
@media(max-width:700px){
    .cs-score-summary { grid-template-columns:1fr; }
    .cs-score-grid { grid-template-columns:1fr; }
}
</style>

{{-- ヘッダー --}}
<div class="cs-topbar">
    <div>
        <div class="cs-kicker">
            <span class="badge blue">Company #{{ $company->id }}</span>
            <span class="badge gray">{{ $company->status }}</span>
            <span class="badge {{ $company->is_killed ? 'red' : 'green' }}">{{ $company->is_killed ? 'killed' : 'active' }}</span>
        </div>
        <h1 class="cs-title">{{ $company->display_name }}</h1>
        <div class="cs-meta">
            <span>{{ $company->industry?->name ?? '業種未設定' }}</span>
            <span>·</span>
            <span>{{ $company->municipality?->prefecture?->name ?? $company->pref ?? '-' }} / {{ $company->municipality?->name ?? $company->city ?? '-' }}</span>
            @if ($company->primaryDomain?->normalized_domain)
                <span>·</span>
                <span style="font-family:monospace;">{{ $company->primaryDomain->normalized_domain }}</span>
            @endif
        </div>
    </div>
    <div class="actions">
        <a class="button light small" href="{{ route('companies.index') }}">companies一覧</a>
        @if ($company->status !== 'merged')
            <a class="button light small" href="{{ route('companies.edit', $company) }}">編集</a>
            <a class="button light small" href="{{ route('companies.merge-form', $company) }}">統合</a>
        @else
            <form method="POST" action="{{ route('companies.undo-merge', $company) }}" onsubmit="return confirm('このcompanyの統合をUndoする？');">
                @csrf
                <button class="button danger small" type="submit">統合Undo</button>
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

{{-- 採点ナビ --}}
<section class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
        <div class="cs-sec-label" style="margin:0">採点ナビ</div>
        <span class="badge {{ $isCurrentScoringQueueTarget ? 'amber' : 'green' }}">
            {{ $isCurrentScoringQueueTarget ? '採点待ち' : '4軸採点済み' }}
        </span>
    </div>
    <div class="cs-nav-row">
        <span style="font-size:12px; color:var(--muted);">採点キュー {{ $scoringQueueCount }} 件</span>
        @if ($previousScoringCompany)
            <a class="button light small" href="{{ route('companies.show', $previousScoringCompany) }}">← 前の未採点 #{{ $previousScoringCompany->id }}</a>
        @endif
        @if ($nextScoringCompany)
            <a class="button small" href="{{ route('companies.show', $nextScoringCompany) }}">次の未採点 #{{ $nextScoringCompany->id }} →</a>
        @endif
    </div>
</section>

{{-- HP解析 --}}
<section class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
        <div class="cs-sec-label" style="margin:0">HP解析（Layer 2）</div>
        @if ($company->primaryDomain)
            <form method="POST" action="{{ route('companies.analyze', $company) }}">
                @csrf
                <button class="button small" type="submit">HP解析を実行</button>
            </form>
        @else
            <span style="font-size:12px; color:var(--muted);">primary_domain未設定のため解析不可</span>
        @endif
    </div>

    @if ($company->primaryDomain)
        <div style="font-size:12px; color:var(--muted); margin-bottom:12px;">
            対象URL：<span style="font-family:monospace;">{{ $company->primaryDomain->url }}</span>
        </div>
    @endif

    @php
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
    @endphp

    @if ($latestFact)
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; margin-bottom:14px;">
            <div class="cs-kv">
                <div class="cs-kv-k">HP改善余地スコア</div>
                <div class="cs-kv-v" style="font-size:22px; font-weight:950;">
                    {{ $latestFact->hp_improvement_score ?? '—' }}<span style="font-size:13px; font-weight:400;"> / 5</span>
                </div>
            </div>
            <div class="cs-kv">
                <div class="cs-kv-k">SSL</div>
                <div class="cs-kv-v">
                    <span class="badge {{ $latestFact->ssl_enabled ? 'green' : 'red' }}">{{ $latestFact->ssl_enabled ? 'あり' : 'なし' }}</span>
                </div>
            </div>
            <div class="cs-kv">
                <div class="cs-kv-k">スマホ対応</div>
                <div class="cs-kv-v">
                    <span class="badge {{ $latestFact->mobile_friendly ? 'green' : 'red' }}">{{ $latestFact->mobile_friendly ? '対応' : '非対応' }}</span>
                </div>
            </div>
            <div class="cs-kv">
                <div class="cs-kv-k">更新状況</div>
                <div class="cs-kv-v">
                    @php
                        $statusLabels = ['active' => '更新中', 'partial_active' => '一部更新', 'stale_1y' => '1年以上停止', 'stale_2y' => '2年以上停止', 'unknown' => '不明'];
                        $statusClasses = ['active' => 'green', 'partial_active' => 'blue', 'stale_1y' => 'amber', 'stale_2y' => 'red', 'unknown' => 'gray'];
                    @endphp
                    <span class="badge {{ $statusClasses[$latestFact->update_status] ?? 'gray' }}">
                        {{ $statusLabels[$latestFact->update_status] ?? $latestFact->update_status }}
                    </span>
                    @if ($latestFact->hp_update_staleness_days !== null)
                        <span style="font-size:11px; color:var(--muted); margin-left:4px;">約{{ $latestFact->hp_update_staleness_days }}日前</span>
                    @endif
                </div>
            </div>
            <div class="cs-kv">
                <div class="cs-kv-k">CMS</div>
                <div class="cs-kv-v">{{ $latestFact->cms_type ?? '不明' }}</div>
            </div>
            <div class="cs-kv">
                <div class="cs-kv-k">お知らせ</div>
                <div class="cs-kv-v">
                    <span class="badge {{ $latestFact->hp_has_news ? 'green' : 'gray' }}">{{ $latestFact->hp_has_news ? 'あり' : 'なし/不明' }}</span>
                </div>
            </div>
            <div class="cs-kv">
                <div class="cs-kv-k">問い合わせ</div>
                <div class="cs-kv-v">{{ $latestFact->contact_method_type ?? '不明' }}</div>
            </div>
            <div class="cs-kv">
                <div class="cs-kv-k">ポータル依存</div>
                <div class="cs-kv-v">
                    @php $pLevel = $latestFact->portal_dependency_level ?? 'none'; @endphp
                    <span class="badge {{ in_array($pLevel, ['medium','high']) ? 'red' : 'green' }}">{{ $pLevel }}</span>
                    @if ($latestFact->hp_has_tabelog)   <span class="badge gray" style="font-size:10px;">食べログ</span> @endif
                    @if ($latestFact->hp_has_hotpepper) <span class="badge gray" style="font-size:10px;">ホットペッパー</span> @endif
                    @if ($latestFact->hp_has_jalan)     <span class="badge gray" style="font-size:10px;">じゃらん/楽天</span> @endif
                    @if ($latestFact->hp_has_suumo)     <span class="badge gray" style="font-size:10px;">SUUMO</span> @endif
                </div>
            </div>
            <div class="cs-kv">
                <div class="cs-kv-k">画像数 / 文字数</div>
                <div class="cs-kv-v">{{ $latestFact->hp_image_count ?? '—' }}枚 / {{ $latestFact->hp_word_count ? number_format($latestFact->hp_word_count) : '—' }}文字</div>
            </div>
            <div class="cs-kv">
                <div class="cs-kv-k">解析日時</div>
                <div class="cs-kv-v" style="font-size:12px;">{{ optional($latestFact->extracted_at)->format('Y-m-d H:i') ?? '—' }}</div>
            </div>
        </div>

        @if ($latestFact->hp_title)
            <div style="font-size:12px; color:var(--muted); border-top:1px solid var(--line); padding-top:10px;">
                <strong>タイトル：</strong>{{ $latestFact->hp_title }}
            </div>
        @endif
    @else
        <div style="font-size:13px; color:var(--muted); padding:12px 0;">
            未解析。「HP解析を実行」ボタンで解析するとHP弱点度の自動提案精度が上がります。
        </div>
    @endif
</section>

{{-- 4軸スコア --}}
<section class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
        <div class="cs-sec-label" style="margin:0">4軸スコア（Layer 2）</div>
        <span class="badge gray">algo: v1</span>
    </div>

    <div class="cs-score-summary" style="margin-bottom:18px;">
        <div class="cs-score-stat opp">
            <div style="font-size:11px; font-weight:900; color:#166534;">機会スコア</div>
            <div class="cs-score-num" style="color:#166534;">{{ $opportunityScore }}<span style="font-size:16px;">/10</span></div>
            <div class="cs-score-sub">hp_weakness + self_update_fit</div>
        </div>
        <div class="cs-score-stat risk">
            <div style="font-size:11px; font-weight:900; color:#92400e;">リスクスコア</div>
            <div class="cs-score-num" style="color:#92400e;">{{ $riskScore }}<span style="font-size:16px;">/10</span></div>
            <div class="cs-score-sub">dev_difficulty + portal_dependence</div>
        </div>
        <div class="cs-score-stat">
            <div style="font-size:11px; font-weight:900; color:var(--muted);">判定</div>
            <div style="margin:6px 0 4px;"><span class="badge {{ $scoreJudgmentClass }}">{{ $scoreJudgment }}</span></div>
            <div class="cs-score-sub">採点済み {{ $scoredAxesCount }} / 4軸</div>
        </div>
    </div>

    <form method="POST" action="{{ route('companies.scores.store', $company) }}">
        @csrf

        @if (count($suggestionPayload) > 0)
            <div style="padding:10px 14px; border-radius:10px; background:#fff7ed; border:1px solid #fed7aa; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
                <span style="font-size:12px; font-weight:900; color:#92400e;">自動提案あり</span>
                <div class="actions">
                    <button type="button" class="button light small" onclick="applyAllScoreSuggestions(false)">全部反映</button>
                    <button type="button" class="button light small" onclick="applyAllScoreSuggestions(true)">未採点だけ反映</button>
                </div>
            </div>
        @endif

        <div class="cs-score-grid">
            @foreach ($scoreAxes as $axis => $meta)
                @php
                    $currentScore      = $scoresByAxis->get($axis);
                    $currentReason     = $currentScore?->reason_json ?? [];
                    $currentNote       = old("scores.$axis.note", $currentReason['note'] ?? '');
                    $currentValue      = old("scores.$axis.value", $currentScore?->value ?? 0);
                    $currentConfidence = old("scores.$axis.confidence", $currentScore?->confidence ?? '0.6');
                    $isRisk            = $meta['group'] === 'リスク';
                    $suggestion        = $scoreSuggestions[$axis] ?? null;
                    $autoSuggestedValue = $currentScore?->auto_suggested_value;
                    $suggestionDelta    = ($autoSuggestedValue !== null && $currentScore) ? ((int)$currentScore->value - (int)$autoSuggestedValue) : null;
                @endphp

                <div class="cs-score-card {{ $isRisk ? 'risk-card' : '' }}">
                    <div class="cs-score-card-head">
                        <div>
                            <div class="cs-axis-key">{{ $axis }}</div>
                            <div class="cs-axis-label">{{ $meta['label'] }}</div>
                        </div>
                        <span class="badge {{ $isRisk ? 'red' : 'green' }}">{{ $meta['group'] }}</span>
                    </div>

                    @if ($suggestion && $suggestion['value'] !== null)
                        <div class="cs-suggestion">
                            <span class="cs-suggestion-label">自動提案：{{ $suggestion['value'] }}点（confidence {{ $suggestion['confidence'] }}）</span>
                            <button type="button" class="button small light" onclick="applyScoreSuggestion('{{ $axis }}', {{ $suggestion['value'] }}, '{{ $suggestion['confidence'] }}')">反映</button>
                        </div>
                        <input type="hidden" name="score_suggestions[{{ $axis }}][value]"        value="{{ $suggestion['value'] }}">
                        <input type="hidden" name="score_suggestions[{{ $axis }}][confidence]"   value="{{ $suggestion['confidence'] }}">
                        <input type="hidden" name="score_suggestions[{{ $axis }}][basis]"        value="{{ $suggestion['basis'] ?? 'auto' }}">
                        <input type="hidden" name="score_suggestions[{{ $axis }}][algo_version]" value="{{ \App\Services\ScoreSuggester::ALGO }}">
                        <input type="hidden" name="score_suggestions[{{ $axis }}][drivers_json]" value="{{ e(json_encode($suggestion['drivers'] ?? [], JSON_UNESCAPED_UNICODE)) }}">
                        <input type="hidden" name="score_suggestions[{{ $axis }}][note]"         value="{{ $suggestion['note'] }}">
                    @endif

                    <div class="field">
                        <label for="score_{{ $axis }}_value">value 0〜5（{{ $meta['polarity'] }}）</label>
                        <select id="score_{{ $axis }}_value" name="scores[{{ $axis }}][value]" data-scored="{{ $currentScore ? '1' : '0' }}" required>
                            @for ($i = 0; $i <= 5; $i++)
                                <option value="{{ $i }}" @selected((string)$currentValue === (string)$i)>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="field">
                        <label for="score_{{ $axis }}_confidence">confidence</label>
                        <select id="score_{{ $axis }}_confidence" name="scores[{{ $axis }}][confidence]" required>
                            <option value="0.3" @selected((string)$currentConfidence === '0.3')>0.3：推測中心</option>
                            <option value="0.6" @selected((string)$currentConfidence === '0.6')>0.6：一部確認</option>
                            <option value="0.9" @selected((string)$currentConfidence === '0.9')>0.9：直接確認</option>
                        </select>
                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <label for="score_{{ $axis }}_note">判断メモ</label>
                        <textarea id="score_{{ $axis }}_note" name="scores[{{ $axis }}][note]" placeholder="何を見てその点数にしたか">{{ $currentNote }}</textarea>
                    </div>

                    <div class="cs-current">
                        @if ($currentScore)
                            <div class="cs-current-val">現在：{{ $currentScore->value }}点</div>
                            <div>confidence {{ $currentScore->confidence }}
                                @if ($autoSuggestedValue !== null)
                                    · auto提案 {{ $autoSuggestedValue }}点
                                    @if ($suggestionDelta === 0)
                                        <span class="badge green" style="font-size:10px;">提案どおり</span>
                                    @else
                                        <span class="badge blue" style="font-size:10px;">手動 {{ $suggestionDelta > 0 ? '+' : '' }}{{ $suggestionDelta }}</span>
                                    @endif
                                @endif
                            </div>
                        @else
                            <div style="color:var(--muted);">未採点</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="actions" style="margin-top:16px;">
            <button class="button" type="submit">4軸スコアを保存</button>
            <button class="button light" type="submit" name="after_action" value="next_scoring">保存して次の未採点へ</button>
        </div>
    </form>
</section>

{{-- kill_flags --}}
<section class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
        <div class="cs-sec-label" style="margin:0">kill_flags</div>
        <span class="badge {{ $company->is_killed ? 'red' : 'green' }}">is_killed={{ $company->is_killed ? 'true' : 'false' }}</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>flag</th><th>note</th><th>source</th><th>flagged_by</th><th>flagged_at</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($company->killFlags as $killFlag)
                <tr>
                    <td><strong>{{ $killFlag->flag }}</strong></td>
                    <td>{{ $killFlag->note ?? '-' }}</td>
                    <td>{{ $killFlag->source ?? '-' }}</td>
                    <td>{{ $killFlag->flagged_by ?? '-' }}</td>
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
                <tr><td colspan="6" class="muted">kill_flagなし</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('companies.kill-flags.store', $company) }}" style="margin-top:14px;">
        @csrf
        <div class="grid">
            <div class="field" style="margin-bottom:0;">
                <label for="flag">追加するkill_flag</label>
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
                <button class="button danger" type="submit">kill_flag追加</button>
            </div>
        </div>
    </form>
</section>

{{-- company基本情報 --}}
<section class="card">
    <div class="cs-sec-label">company基本情報</div>
    <div class="cs-kv-grid">
        <div class="cs-kv"><div class="cs-kv-k">status</div><div class="cs-kv-v"><span class="badge gray">{{ $company->status }}</span></div></div>
        <div class="cs-kv"><div class="cs-kv-k">display_name</div><div class="cs-kv-v">{{ $company->display_name }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">legal_name</div><div class="cs-kv-v">{{ $company->legal_name ?? '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">name_norm</div><div class="cs-kv-v">{{ $company->name_norm ?? '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">industry</div><div class="cs-kv-v">{{ $company->industry?->name ?? '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">municipality</div><div class="cs-kv-v">{{ $company->municipality?->prefecture?->name ?? $company->pref ?? '-' }} / {{ $company->municipality?->name ?? $company->city ?? '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">corporate_number</div><div class="cs-kv-v">{{ $company->corporate_number ?? '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">primary_domain</div><div class="cs-kv-v" style="overflow-wrap:anywhere;">{{ $company->primaryDomain?->url ?? '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">normalized_domain</div><div class="cs-kv-v">{{ $company->primaryDomain?->normalized_domain ?? '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">merged_into</div><div class="cs-kv-v">{{ $company->mergedInto ? '#'.$company->mergedInto->id.' '.$company->mergedInto->display_name : '-' }}</div></div>
        <div class="cs-kv"><div class="cs-kv-k">created_at</div><div class="cs-kv-v">{{ optional($company->created_at)->format('Y-m-d H:i') ?? '-' }}</div></div>
    </div>

    @if ($company->mergedChildren->count())
        <div style="margin-top:16px;">
            <div class="cs-sec-label">このcompanyに統合されたcompany</div>
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
    <div class="cs-sec-label">domains</div>
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
    <div class="cs-sec-label">source links</div>
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

@push('scripts')
<script>
var scoreSuggestions = @json($suggestionPayload ?? []);

function applyScoreSuggestion(axis, value, confidence) {
    var v = document.getElementById('score_' + axis + '_value');
    var c = document.getElementById('score_' + axis + '_confidence');
    if (v) v.value = String(value);
    if (c) c.value = String(confidence);
}

function applyAllScoreSuggestions(onlyUnscored) {
    scoreSuggestions.forEach(function(s) {
        var v = document.getElementById('score_' + s.axis + '_value');
        if (onlyUnscored && v && v.dataset.scored === '1') return;
        applyScoreSuggestion(s.axis, s.value, s.confidence);
    });
}
</script>
@endpush
@endsection
