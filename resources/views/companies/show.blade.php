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
.cs-score-card { border:1px solid var(--line); border-radius:14px; padding:14px; background:var(--card); }
.cs-score-card.opp { border-color:#bbf7d0; background:#f0fdf4; }
.cs-score-card.risk { border-color:#fed7aa; background:#fffaf3; }
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
    @if ($latestFact)
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

{{-- 4軸スコア --}}
<section class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:14px;">
        <div class="section-label">4軸スコア（Layer 2）</div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <span style="font-size:11px;color:var(--muted);">機会 {{ $opportunityScore }}/10 · リスク {{ $riskScore }}/10 · 採点 {{ $scoredAxesCount }}/4</span>
            <span class="badge {{ $scoreJudgmentClass }}">{{ $scoreJudgment }}</span>
        </div>
    </div>

    <form method="POST" action="{{ route('companies.scores.store', $company) }}">
        @csrf

        <div class="cs-score-row">
            @foreach ($scoreAxes as $axis => $meta)
                @php
                    $currentScore       = $scoresByAxis->get($axis);
                    $currentReason      = $currentScore?->reason_json ?? [];
                    $currentNote        = old("scores.$axis.note", $currentReason['note'] ?? '');
                    $currentValue       = old("scores.$axis.value", $currentScore?->value ?? 0);
                    $currentConfidence  = old("scores.$axis.confidence", $currentScore?->confidence ?? '0.6');
                    $isRisk             = $meta['group'] === 'リスク';
                    $suggestion         = $scoreSuggestions[$axis] ?? null;
                    $autoSuggestedValue = $currentScore?->auto_suggested_value;
                    $suggestionDelta    = ($autoSuggestedValue !== null && $currentScore)
                        ? ((int)$currentScore->value - (int)$autoSuggestedValue) : null;
                @endphp

                <div class="cs-score-card {{ $isRisk ? 'risk' : 'opp' }}">
                    <div class="cs-score-card-top">
                        <div>
                            <div class="cs-axis-key">{{ $axis }}</div>
                            <div class="cs-axis-label">{{ $meta['label'] }}</div>
                        </div>
                        <span class="badge {{ $isRisk ? 'red' : 'green' }}" style="font-size:10px;">{{ $meta['group'] }}</span>
                    </div>

                    <div class="cs-score-val" style="color:{{ $isRisk ? '#92400e' : '#166534' }};">
                        {{ $currentScore ? $currentScore->value : '—' }}<span style="font-size:14px;font-weight:400;color:var(--muted);">/5</span>
                    </div>
                    <div class="cs-score-sub">{{ $meta['polarity'] }}</div>

                    @if ($suggestion && $suggestion['value'] !== null)
                        <div class="cs-suggestion-bar">自動提案：{{ $suggestion['value'] }}点（{{ $suggestion['confidence'] }}）</div>
                        <input type="hidden" name="score_suggestions[{{ $axis }}][value]"        value="{{ $suggestion['value'] }}">
                        <input type="hidden" name="score_suggestions[{{ $axis }}][confidence]"   value="{{ $suggestion['confidence'] }}">
                        <input type="hidden" name="score_suggestions[{{ $axis }}][basis]"        value="{{ $suggestion['basis'] ?? 'auto' }}">
                        <input type="hidden" name="score_suggestions[{{ $axis }}][algo_version]" value="{{ \App\Services\ScoreSuggester::ALGO }}">
                        <input type="hidden" name="score_suggestions[{{ $axis }}][drivers_json]" value="{{ e(json_encode($suggestion['drivers'] ?? [], JSON_UNESCAPED_UNICODE)) }}">
                        <input type="hidden" name="score_suggestions[{{ $axis }}][note]"         value="{{ $suggestion['note'] }}">
                    @endif

                    <div class="field" style="margin-top:10px;margin-bottom:6px;">
                        <label for="score_{{ $axis }}_value" style="font-size:11px;">value 0〜5</label>
                        <select id="score_{{ $axis }}_value" name="scores[{{ $axis }}][value]" data-scored="{{ $currentScore ? '1' : '0' }}" required>
                            @for ($i = 0; $i <= 5; $i++)
                                <option value="{{ $i }}" @selected((string)$currentValue === (string)$i)>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="field" style="margin-bottom:6px;">
                        <label for="score_{{ $axis }}_confidence" style="font-size:11px;">confidence</label>
                        <select id="score_{{ $axis }}_confidence" name="scores[{{ $axis }}][confidence]" required>
                            <option value="0.3" @selected((string)$currentConfidence === '0.3')>0.3 推測</option>
                            <option value="0.6" @selected((string)$currentConfidence === '0.6')>0.6 一部確認</option>
                            <option value="0.9" @selected((string)$currentConfidence === '0.9')>0.9 直接確認</option>
                        </select>
                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <label for="score_{{ $axis }}_note" style="font-size:11px;">メモ</label>
                        <textarea id="score_{{ $axis }}_note" name="scores[{{ $axis }}][note]" rows="2" placeholder="判断メモ">{{ $currentNote }}</textarea>
                    </div>

                    @if ($currentScore && $autoSuggestedValue !== null)
                        <div class="cs-current-bar">
                            auto提案 {{ $autoSuggestedValue }}点
                            @if ($suggestionDelta === 0)
                                <span class="badge green" style="font-size:10px;">提案どおり</span>
                            @else
                                <span class="badge blue" style="font-size:10px;">手動 {{ $suggestionDelta > 0 ? '+' : '' }}{{ $suggestionDelta }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="actions" style="margin-top:14px;">
            <button class="button" type="submit">4軸スコアを保存</button>
            <button class="button light" type="submit" name="after_action" value="next_scoring">保存して次の未採点へ</button>
        </div>
    </form>
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
