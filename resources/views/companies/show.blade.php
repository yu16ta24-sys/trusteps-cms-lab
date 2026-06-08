@extends('layouts.app', ['title' => 'company詳細 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content company-show">
        @php
            $hpWeakness = optional($scoresByAxis->get('hp_weakness'))->value;
            $selfUpdateFit = optional($scoresByAxis->get('self_update_fit'))->value;
            $devDifficulty = optional($scoresByAxis->get('dev_difficulty'))->value;
            $portalDependence = optional($scoresByAxis->get('portal_dependence'))->value;

            $scoredAxesCount = collect([$hpWeakness, $selfUpdateFit, $devDifficulty, $portalDependence])
                ->filter(fn ($value) => $value !== null)
                ->count();

            $opportunityScore = ($hpWeakness ?? 0) + ($selfUpdateFit ?? 0);
            $riskScore = ($devDifficulty ?? 0) + ($portalDependence ?? 0);

            if ($scoredAxesCount < 4) {
                $scoreJudgment = '未採点あり';
                $scoreJudgmentClass = 'gray';
            } elseif ($opportunityScore >= 7 && $riskScore <= 3) {
                $scoreJudgment = '高機会・低リスク';
                $scoreJudgmentClass = 'green';
            } elseif ($opportunityScore >= 7 && $riskScore >= 7) {
                $scoreJudgment = '高機会・高リスク';
                $scoreJudgmentClass = 'blue';
            } elseif ($opportunityScore <= 3 && $riskScore >= 7) {
                $scoreJudgment = '低機会・高リスク';
                $scoreJudgmentClass = 'red';
            } elseif ($opportunityScore <= 3 && $riskScore <= 3) {
                $scoreJudgment = '低機会・低リスク';
                $scoreJudgmentClass = 'gray';
            } else {
                $scoreJudgment = '要確認';
                $scoreJudgmentClass = 'blue';
            }
        @endphp

        <style>
            .company-show .stack { display: grid; gap: 18px; }
            .company-show .hero {
                position: relative;
                overflow: hidden;
                background: linear-gradient(135deg, #eff6ff 0%, #ffffff 45%, #f8fafc 100%);
            }
            .company-show .hero::after {
                content: "";
                position: absolute;
                inset: auto -80px -80px auto;
                width: 240px;
                height: 240px;
                background: radial-gradient(circle, rgba(37,99,235,.16), transparent 60%);
                pointer-events: none;
            }
            .company-show .hero-head { position: relative; z-index: 1; }
            .company-show .eyebrow {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                font-weight: 800;
                color: #475467;
                margin-bottom: 10px;
            }
            .company-show .hero-title {
                margin: 0;
                font-size: clamp(30px, 5vw, 44px);
                line-height: 1.05;
                letter-spacing: -.02em;
            }
            .company-show .hero-sub {
                margin: 10px 0 0;
                color: #667085;
                max-width: 720px;
                font-size: 15px;
            }
            .company-show .hero-meta {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                margin-top: 18px;
            }
            .company-show .section-card {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 22px;
                box-shadow: var(--shadow);
                padding: 24px;
            }
            .company-show .section-head { margin-bottom: 18px; }
            .company-show .section-title {
                margin: 0;
                font-size: 28px;
                letter-spacing: -.02em;
            }
            .company-show .summary-grid { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
            .company-show .summary-card {
                padding: 22px;
                border-radius: 20px;
                border: 1px solid #dbe4f0;
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            }
            .company-show .summary-card.opportunity { background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%); }
            .company-show .summary-card.risk { background: linear-gradient(180deg, #fff7ed 0%, #ffffff 100%); }
            .company-show .summary-card h3 {
                margin: 12px 0 4px;
                font-size: 34px;
                line-height: 1;
                letter-spacing: -.03em;
            }
            .company-show .summary-card p { margin: 0; }
            .company-show .score-grid { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
            .company-show .score-card {
                border-radius: 22px;
                padding: 20px;
                border: 1px solid #dbe4f0;
                background: linear-gradient(180deg, #fbfdff 0%, #ffffff 100%);
            }
            .company-show .score-card.risk {
                background: linear-gradient(180deg, #fffaf3 0%, #ffffff 100%);
            }
            .company-show .score-card .axis {
                font-size: 14px;
                font-weight: 800;
                color: #344054;
                margin-bottom: 2px;
            }
            .company-show .score-card .label {
                font-size: 28px;
                font-weight: 800;
                letter-spacing: -.02em;
                margin: 0 0 10px;
            }
            .company-show .score-card .guide {
                padding: 12px 14px;
                border-radius: 14px;
                background: rgba(255,255,255,.8);
                border: 1px dashed #dbe4f0;
                font-size: 12px;
                color: #667085;
                line-height: 1.65;
                margin-bottom: 14px;
            }
            .company-show .score-now {
                margin-top: 12px;
                padding: 14px;
                border-radius: 16px;
                background: #ffffff;
                border: 1px solid #dbe4f0;
                box-shadow: inset 0 1px 0 rgba(255,255,255,.8);
            }
            .company-show .score-now .point {
                font-size: 30px;
                line-height: 1;
                font-weight: 900;
                letter-spacing: -.03em;
                margin-bottom: 6px;
            }
            .company-show .kv-grid { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
            .company-show .kv {
                padding: 18px;
                border-radius: 18px;
                border: 1px solid #e8eef6;
                background: #f8fafc;
            }
            .company-show .kv .k { font-size: 12px; color: #667085; font-weight: 800; margin-bottom: 8px; }
            .company-show .kv .v { font-size: 16px; font-weight: 700; word-break: break-word; }
            .company-show .subsection-title {
                margin: 24px 0 12px;
                font-size: 22px;
                letter-spacing: -.02em;
            }
            .company-show .empty-box {
                padding: 16px;
                border: 1px dashed #d0d5dd;
                border-radius: 16px;
                color: #667085;
                background: #fbfcfe;
            }
        </style>

        <div class="stack">
            <section class="card hero">
                <div class="row hero-head">
                    <div>
                        <div class="eyebrow">
                            <span class="badge blue">Company #{{ $company->id }}</span>
                            <span class="badge gray">{{ $company->status }}</span>
                            <span class="badge {{ $company->is_killed ? 'red' : 'green' }}">is_killed={{ $company->is_killed ? 'true' : 'false' }}</span>
                        </div>
                        <h1 class="hero-title">{{ $company->display_name }}</h1>
                        <p class="hero-sub">
                            {{ $company->industry?->name ?? '業種未設定' }}
                            ・
                            {{ $company->municipality?->prefecture?->name ?? $company->pref ?? '都道府県未設定' }} / {{ $company->municipality?->name ?? $company->city ?? '市区町村未設定' }}
                        </p>
                        <div class="hero-meta">
                            @if ($company->primaryDomain?->normalized_domain)
                                <span class="badge gray">domain: {{ $company->primaryDomain->normalized_domain }}</span>
                            @endif
                            @if ($company->corporate_number)
                                <span class="badge gray">法人番号: {{ $company->corporate_number }}</span>
                            @endif
                            <span class="badge gray">created_at: {{ optional($company->created_at)->format('Y-m-d H:i') ?? '-' }}</span>
                        </div>
                    </div>
                    <div class="actions">
                        <a class="button light" href="{{ route('companies.index') }}">companies一覧へ</a>
                        <a class="button light" href="{{ route('source-records.index') }}">source_recordsへ</a>
                        @if ($company->status !== 'merged')
                            <a class="button" href="{{ route('companies.merge-form', $company) }}">このcompanyを統合</a>
                        @else
                            <form method="POST" action="{{ route('companies.undo-merge', $company) }}" onsubmit="return confirm('このcompanyの統合をUndoする？');">
                                @csrf
                                <button class="button danger" type="submit">統合をUndo</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if (session('status'))
                    <div class="status" style="margin-top:20px;">{{ session('status') }}</div>
                @endif

                @if ($company->status === 'merged')
                    <div class="error" style="margin-top:20px; margin-bottom:0;">
                        このcompanyは統合済み。統合先：
                        @if ($company->mergedInto)
                            <a href="{{ route('companies.show', $company->mergedInto) }}">#{{ $company->mergedInto->id }} {{ $company->mergedInto->display_name }}</a>
                        @else
                            不明
                        @endif
                    </div>
                @endif
            </section>

            <section class="section-card">
                <div class="section-head row">
                    <div>
                        <h2 class="section-title">HP観測メモ</h2>
                        <p class="muted" style="margin:8px 0 0;">まずは軽量メモだけ保存する。ここを元に、次段階で4軸スコアの自動提案へつなげる。</p>
                    </div>
                    <span class="badge gray">manual observation</span>
                </div>

                @php
                    $homepageStatus = old('homepage_status', $hpObservation['homepage_status'] ?? 'unknown');
                    $sslStatus = old('ssl_status', $hpObservation['ssl_status'] ?? 'unknown');
                    $mobileStatus = old('mobile_status', $hpObservation['mobile_status'] ?? 'unknown');
                    $updateStatus = old('update_status', $hpObservation['update_status'] ?? 'unknown');
                    $contactStatus = old('contact_status', $hpObservation['contact_status'] ?? 'unknown');
                    $cmsGuess = old('cms_guess', $hpObservation['cms_guess'] ?? 'unknown');
                    $portalDependency = old('portal_dependency', $hpObservation['portal_dependency'] ?? 'unknown');
                    $updateTargetsText = old('update_targets_text', $hpObservation['update_targets_text'] ?? '');
                @endphp

                @if ($errors->any())
                    <div class="error" style="margin-bottom:18px;">
                        入力エラーがある。内容を確認して保存し直して。
                    </div>
                @endif

                <form method="POST" action="{{ route('companies.hp-observation-memo.store', $company) }}">
                    @csrf

                    <div class="grid kv-grid">
                        <div class="field" style="margin-bottom:0;">
                            <label for="homepage_status">HP状態</label>
                            <select id="homepage_status" name="homepage_status">
                                <option value="unknown" @selected($homepageStatus === 'unknown')>不明</option>
                                <option value="exists" @selected($homepageStatus === 'exists')>公式HPあり</option>
                                <option value="none" @selected($homepageStatus === 'none')>公式HPなし</option>
                            </select>
                        </div>

                        <div class="field" style="margin-bottom:0;">
                            <label for="ssl_status">SSL</label>
                            <select id="ssl_status" name="ssl_status">
                                <option value="unknown" @selected($sslStatus === 'unknown')>不明</option>
                                <option value="enabled" @selected($sslStatus === 'enabled')>あり</option>
                                <option value="disabled" @selected($sslStatus === 'disabled')>なし/怪しい</option>
                            </select>
                        </div>

                        <div class="field" style="margin-bottom:0;">
                            <label for="mobile_status">スマホ対応</label>
                            <select id="mobile_status" name="mobile_status">
                                <option value="unknown" @selected($mobileStatus === 'unknown')>不明</option>
                                <option value="good" @selected($mobileStatus === 'good')>良い</option>
                                <option value="weak" @selected($mobileStatus === 'weak')>微妙</option>
                                <option value="none" @selected($mobileStatus === 'none')>なし/崩れ大</option>
                            </select>
                        </div>

                        <div class="field" style="margin-bottom:0;">
                            <label for="update_status">更新状態</label>
                            <select id="update_status" name="update_status">
                                <option value="unknown" @selected($updateStatus === 'unknown')>不明</option>
                                <option value="active" @selected($updateStatus === 'active')>動いている</option>
                                <option value="stale" @selected($updateStatus === 'stale')>止まっている/古い</option>
                            </select>
                        </div>

                        <div class="field" style="margin-bottom:0;">
                            <label for="contact_status">問い合わせ導線</label>
                            <select id="contact_status" name="contact_status">
                                <option value="unknown" @selected($contactStatus === 'unknown')>不明</option>
                                <option value="good" @selected($contactStatus === 'good')>良い</option>
                                <option value="weak" @selected($contactStatus === 'weak')>微妙</option>
                                <option value="none" @selected($contactStatus === 'none')>なし/弱い</option>
                            </select>
                        </div>

                        <div class="field" style="margin-bottom:0;">
                            <label for="cms_guess">CMS/構造推定</label>
                            <select id="cms_guess" name="cms_guess">
                                <option value="unknown" @selected($cmsGuess === 'unknown')>不明</option>
                                <option value="wordpress" @selected($cmsGuess === 'wordpress')>WordPressっぽい</option>
                                <option value="wix" @selected($cmsGuess === 'wix')>Wixっぽい</option>
                                <option value="jimdo" @selected($cmsGuess === 'jimdo')>Jimdoっぽい</option>
                                <option value="static" @selected($cmsGuess === 'static')>静的HTMLっぽい</option>
                                <option value="other" @selected($cmsGuess === 'other')>その他</option>
                            </select>
                        </div>

                        <div class="field" style="margin-bottom:0;">
                            <label for="portal_dependency">SNS/ポータル依存</label>
                            <select id="portal_dependency" name="portal_dependency">
                                <option value="unknown" @selected($portalDependency === 'unknown')>不明</option>
                                <option value="low" @selected($portalDependency === 'low')>低い</option>
                                <option value="medium" @selected($portalDependency === 'medium')>中くらい</option>
                                <option value="high" @selected($portalDependency === 'high')>高い</option>
                            </select>
                        </div>

                        <div class="field" style="margin-bottom:0;">
                            <label for="update_targets_text">更新対象メモ</label>
                            <input id="update_targets_text" name="update_targets_text" type="text" value="{{ $updateTargetsText }}" placeholder="例：お知らせ停止、施工事例あり、ブログ放置">
                        </div>
                    </div>

                    <div class="field" style="margin-top:18px;">
                        <label for="observation_note">観測メモ</label>
                        <textarea id="observation_note" name="observation_note" rows="5" placeholder="HPを見て気づいたこと。後で4軸スコア自動提案の根拠にする。">{{ old('observation_note', $company->hp_observation_note ?? '') }}</textarea>
                    </div>

                    <div class="row" style="margin-top:16px; align-items:center;">
                        <button class="button" type="submit">HP観測メモを保存</button>
                        <div class="muted" style="font-size:13px;">
                            最終保存：{{ optional($company->hp_observed_at)->format('Y-m-d H:i:s') ?? '-' }}
                            @if ($company->hp_observed_by)
                                / {{ $company->hp_observed_by }}
                            @endif
                        </div>
                    </div>
                </form>
            </section>

            <section class="section-card">
                <div class="section-head row">
                    <div>
                        <h2 class="section-title">4軸スコア</h2>
                        <p class="muted" style="margin:8px 0 0;">機会2軸・リスク2軸を手動評価する。機会とリスクは合算しない。</p>
                    </div>
                    <span class="badge gray">algo_version=v1</span>
                </div>

                <div class="grid summary-grid">
                    <div class="summary-card opportunity">
                        <span class="badge green">機会スコア</span>
                        <h3>{{ $opportunityScore }} / 10</h3>
                        <p class="muted">hp_weakness + self_update_fit</p>
                    </div>
                    <div class="summary-card risk">
                        <span class="badge red">リスクスコア</span>
                        <h3>{{ $riskScore }} / 10</h3>
                        <p class="muted">dev_difficulty + portal_dependence</p>
                    </div>
                    <div class="summary-card">
                        <span class="badge {{ $scoreJudgmentClass }}">簡易判定</span>
                        <h3 style="font-size:28px;">{{ $scoreJudgment }}</h3>
                        <p class="muted">採点済み：{{ $scoredAxesCount }} / 4軸</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('companies.scores.store', $company) }}" style="margin-top:18px;">
                    @csrf

                    <div class="grid score-grid">
                        @foreach ($scoreAxes as $axis => $meta)
                            @php
                                $currentScore = $scoresByAxis->get($axis);
                                $currentReason = $currentScore?->reason_json ?? [];
                                $currentNote = old("scores.$axis.note", $currentReason['note'] ?? '');
                                $currentValue = old("scores.$axis.value", $currentScore?->value ?? 0);
                                $currentConfidence = old("scores.$axis.confidence", $currentScore?->confidence ?? '0.6');
                                $isRisk = $meta['group'] === 'リスク';
                            @endphp

                            <div class="score-card {{ $isRisk ? 'risk' : '' }}">
                                <div class="row" style="align-items:flex-start; margin-bottom:10px;">
                                    <div>
                                        <div class="axis">{{ $axis }}</div>
                                        <div class="label">{{ $meta['label'] }}</div>
                                    </div>
                                    <span class="badge {{ $isRisk ? 'red' : 'green' }}">{{ $meta['group'] }}</span>
                                </div>

                                <p class="muted" style="font-size:13px; line-height:1.75; margin-top:0;">{{ $meta['description'] }}</p>
                                <div class="guide">
                                    <strong>0</strong>：{{ $meta['anchor_0'] }}<br>
                                    <strong>3</strong>：{{ $meta['anchor_3'] }}<br>
                                    <strong>5</strong>：{{ $meta['anchor_5'] }}
                                </div>

                                <div class="field">
                                    <label for="score_{{ $axis }}_value">value 0〜5（{{ $meta['polarity'] }}）</label>
                                    <select id="score_{{ $axis }}_value" name="scores[{{ $axis }}][value]" required>
                                        @for ($i = 0; $i <= 5; $i++)
                                            <option value="{{ $i }}" @selected((string) $currentValue === (string) $i)>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="score_{{ $axis }}_confidence">confidence</label>
                                    <select id="score_{{ $axis }}_confidence" name="scores[{{ $axis }}][confidence]" required>
                                        <option value="0.3" @selected((string) $currentConfidence === '0.3')>0.3：推測中心</option>
                                        <option value="0.6" @selected((string) $currentConfidence === '0.6')>0.6：一部確認</option>
                                        <option value="0.9" @selected((string) $currentConfidence === '0.9')>0.9：直接確認</option>
                                    </select>
                                </div>

                                <div class="field" style="margin-bottom:0;">
                                    <label for="score_{{ $axis }}_note">判断メモ</label>
                                    <textarea id="score_{{ $axis }}_note" name="scores[{{ $axis }}][note]" placeholder="何を見てその点数にしたか">{{ $currentNote }}</textarea>
                                </div>

                                @if ($currentScore)
                                    <div class="score-now">
                                        <div class="point">現在：{{ $currentScore->value }}点</div>
                                        <div class="muted" style="font-size:12px; line-height:1.7;">
                                            confidence {{ $currentScore->confidence }}<br>
                                            scored_by：{{ $currentScore->scored_by ?? '-' }}<br>
                                            scored_at：{{ optional($currentScore->scored_at)->format('Y-m-d H:i:s') ?? '-' }}
                                        </div>
                                    </div>
                                @else
                                    <div class="score-now">
                                        <div class="muted" style="font-size:14px;">未採点</div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div style="margin-top:18px;">
                        <button class="button" type="submit">4軸スコアを保存</button>
                    </div>
                </form>
            </section>

            <section class="section-card">
                <div class="section-head row">
                    <div>
                        <h2 class="section-title">kill_flags</h2>
                        <p class="muted" style="margin:8px 0 0;">手動で即時除外理由を付ける。1つでもflagがあれば is_killed=true。</p>
                    </div>
                    <span class="badge {{ $company->is_killed ? 'red' : 'green' }}">is_killed={{ $company->is_killed ? 'true' : 'false' }}</span>
                </div>

                <div class="table-wrap" style="margin-top:16px;">
                    <table>
                        <thead>
                        <tr>
                            <th>flag</th>
                            <th>note</th>
                            <th>source</th>
                            <th>flagged_by</th>
                            <th>flagged_at</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($company->killFlags as $killFlag)
                            <tr>
                                <td><strong>{{ $killFlag->flag }}</strong></td>
                                <td>{{ $killFlag->note ?? '-' }}</td>
                                <td>{{ $killFlag->source ?? '-' }}</td>
                                <td>{{ $killFlag->flagged_by ?? '-' }}</td>
                                <td>{{ optional($killFlag->flagged_at)->format('Y-m-d H:i:s') ?? '-' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('companies.kill-flags.destroy', [$company, $killFlag]) }}" onsubmit="return confirm('このkill_flagを解除する？');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button small danger" type="submit">解除</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="muted">kill_flagなし</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <form method="POST" action="{{ route('companies.kill-flags.store', $company) }}" style="margin-top:18px;">
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
                            <label for="note">note</label>
                            <input id="note" name="note" type="text" placeholder="何を見て判断したか">
                        </div>
                        <div class="field" style="margin-bottom:0; align-self:end;">
                            <button class="button danger" type="submit">kill_flag追加</button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="section-card">
                <div class="section-head">
                    <h2 class="section-title">company基本情報</h2>
                    <p class="muted" style="margin:8px 0 0;">分析や名寄せの土台になる基本データ。</p>
                </div>

                <div class="grid kv-grid">
                    <div class="kv"><div class="k">status</div><div class="v"><span class="badge gray">{{ $company->status }}</span></div></div>
                    <div class="kv"><div class="k">display_name</div><div class="v">{{ $company->display_name }}</div></div>
                    <div class="kv"><div class="k">legal_name</div><div class="v">{{ $company->legal_name ?? '-' }}</div></div>
                    <div class="kv"><div class="k">name_norm</div><div class="v">{{ $company->name_norm ?? '-' }}</div></div>
                    <div class="kv"><div class="k">industry</div><div class="v">{{ $company->industry?->name ?? '-' }}</div></div>
                    <div class="kv"><div class="k">municipality</div><div class="v">{{ $company->municipality?->prefecture?->name ?? $company->pref ?? '-' }} / {{ $company->municipality?->name ?? $company->city ?? '-' }}</div></div>
                    <div class="kv"><div class="k">corporate_number</div><div class="v">{{ $company->corporate_number ?? '-' }}</div></div>
                    <div class="kv"><div class="k">primary_domain</div><div class="v">{{ $company->primaryDomain?->url ?? '-' }}</div></div>
                    <div class="kv"><div class="k">normalized_domain</div><div class="v">{{ $company->primaryDomain?->normalized_domain ?? '-' }}</div></div>
                    <div class="kv"><div class="k">merge_previous_status</div><div class="v">{{ $company->merge_previous_status ?? '-' }}</div></div>
                    <div class="kv"><div class="k">merged_into</div><div class="v">{{ $company->mergedInto ? '#' . $company->mergedInto->id . ' ' . $company->mergedInto->display_name : '-' }}</div></div>
                    <div class="kv"><div class="k">created_at</div><div class="v">{{ optional($company->created_at)->format('Y-m-d H:i:s') ?? '-' }}</div></div>
                </div>

                @if ($company->mergedChildren->count())
                    <h3 class="subsection-title">このcompanyに統合されたcompany</h3>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>display_name</th>
                                <th>status</th>
                                <th></th>
                            </tr>
                            </thead>
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
                @endif
            </section>

            <section class="section-card">
                <div class="section-head">
                    <h2 class="section-title">domains</h2>
                    <p class="muted" style="margin:8px 0 0;">companyに紐づくWeb資産。</p>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>url</th>
                            <th>normalized_domain</th>
                            <th>role</th>
                            <th>primary</th>
                        </tr>
                        </thead>
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

            <section class="section-card">
                <div class="section-head">
                    <h2 class="section-title">source links</h2>
                    <p class="muted" style="margin:8px 0 0;">このcompanyの元になったsource_recordとの確定リンク。</p>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>source_record_id</th>
                            <th>match_type</th>
                            <th>source_type</th>
                            <th>domain</th>
                            <th></th>
                        </tr>
                        </thead>
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
        </div>
    </main>
@endsection
