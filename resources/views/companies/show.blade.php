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

            $latestHpFact = $latestHpSnapshot?->fact;
            $latestTargetsById = $latestHpSnapshot?->updateTargets?->keyBy('update_target_id') ?? collect();

            $triStateValue = function ($value) {
                if ($value === true) {
                    return '1';
                }
                if ($value === false) {
                    return '0';
                }
                return 'unknown';
            };

            $valueLabel = function ($options, $value) {
                return $options[$value] ?? '不明';
            };
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
            .company-show .observation-current {
                border: 1px solid #dbeafe;
                background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
                border-radius: 20px;
                padding: 18px;
                margin-bottom: 18px;
            }
            .company-show .target-grid { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
            .company-show .target-card {
                border: 1px solid #e8eef6;
                background: #fbfcfe;
                border-radius: 16px;
                padding: 14px;
            }
            .company-show .target-card .target-name {
                font-weight: 900;
                margin-bottom: 10px;
                line-height: 1.35;
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
                        <h2 class="section-title">HP観測</h2>
                        <p class="muted" style="margin:8px 0 0;">4軸スコア自動提案の材料になるHP状態を手動で記録する。ここでは自動採点せず、まず事実データを貯める。</p>
                    </div>
                    <span class="badge blue">manual_v1</span>
                </div>

                @if ($latestHpSnapshot)
                    <div class="observation-current">
                        <div class="row" style="align-items:flex-start; gap:16px;">
                            <div>
                                <div class="muted" style="font-size:12px; font-weight:800;">最新観測</div>
                                <h3 style="margin:6px 0 8px; font-size:22px;">{{ optional($latestHpSnapshot->crawled_at)->format('Y-m-d H:i') ?? '-' }}</h3>
                                <p class="muted" style="margin:0; overflow-wrap:anywhere;">
                                    domain: {{ $latestHpSnapshot->domain?->normalized_domain ?? '-' }} / status: {{ $latestHpSnapshot->http_status ?? '-' }}
                                </p>
                            </div>
                            <div class="actions">
                                <span class="badge gray">SSL: {{ $valueLabel($hpObservationOptions['ssl_enabled'], $triStateValue($latestHpFact?->ssl_enabled)) }}</span>
                                <span class="badge gray">SP: {{ $valueLabel($hpObservationOptions['mobile_friendly'], $triStateValue($latestHpFact?->mobile_friendly)) }}</span>
                                <span class="badge gray">更新: {{ $valueLabel($hpObservationOptions['update_status'], $latestHpFact?->update_status ?? 'unknown') }}</span>
                                <span class="badge gray">CMS: {{ $valueLabel($hpObservationOptions['cms_type'], $latestHpFact?->cms_type ?? 'unknown') }}</span>
                            </div>
                        </div>

                        @if ($latestHpSnapshot->observation_note)
                            <div class="empty-box" style="margin-top:14px;">{{ $latestHpSnapshot->observation_note }}</div>
                        @endif

                        @if ($latestHpSnapshot->updateTargets->count())
                            <div class="table-wrap" style="margin-top:16px;">
                                <table>
                                    <thead>
                                    <tr>
                                        <th>更新対象</th>
                                        <th>存在</th>
                                        <th>停止</th>
                                        <th>最終更新</th>
                                        <th>メモ</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($latestHpSnapshot->updateTargets as $snapshotTarget)
                                        @php
                                            $evidence = $snapshotTarget->evidence_json ?? [];
                                        @endphp
                                        <tr>
                                            <td>{{ $snapshotTarget->updateTarget?->name ?? '-' }}</td>
                                            <td>{{ is_null($snapshotTarget->is_present) ? '-' : ($snapshotTarget->is_present ? 'あり' : 'なし') }}</td>
                                            <td>{{ is_null($snapshotTarget->is_stopped) ? '-' : ($snapshotTarget->is_stopped ? '停止' : '動いてる') }}</td>
                                            <td>{{ optional($snapshotTarget->last_update_date)->format('Y-m-d') ?? '-' }}</td>
                                            <td>{{ $evidence['note'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="empty-box" style="margin-bottom:18px;">まだHP観測データなし。まず1件だけ見たまま入力して、次の4軸自動提案の材料にする。</div>
                @endif

                @if ($company->domains->isEmpty())
                    <div class="error" style="margin-bottom:0;">domainがないためHP観測を保存できない。source_recordからURL付きでcompany化するか、domain登録UI追加が必要。</div>
                @else
                    <form method="POST" action="{{ route('companies.hp-observation.store', $company) }}">
                        @csrf

                        <div class="grid kv-grid">
                            <div class="field">
                                <label for="hp_domain_id">観測対象domain</label>
                                <select id="hp_domain_id" name="domain_id" required>
                                    @foreach ($company->domains as $domain)
                                        <option value="{{ $domain->id }}" @selected((string) old('domain_id', $company->primary_domain_id ?: $company->domains->first()?->id) === (string) $domain->id)>
                                            #{{ $domain->id }} {{ $domain->normalized_domain ?? $domain->url }}{{ $domain->is_primary ? '（primary）' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="requested_url">requested_url</label>
                                <input id="requested_url" name="requested_url" type="text" value="{{ old('requested_url', $company->primaryDomain?->url) }}" placeholder="観測したURL">
                            </div>
                            <div class="field">
                                <label for="final_url">final_url</label>
                                <input id="final_url" name="final_url" type="text" value="{{ old('final_url', $latestHpSnapshot?->final_url) }}" placeholder="リダイレクト後URL。空でもOK">
                            </div>
                            <div class="field">
                                <label for="http_status">HTTP status</label>
                                <input id="http_status" name="http_status" type="number" min="100" max="599" value="{{ old('http_status', $latestHpSnapshot?->http_status) }}" placeholder="200など。空でもOK">
                            </div>
                            <div class="field">
                                <label for="crawled_at">観測日時</label>
                                <input id="crawled_at" name="crawled_at" type="datetime-local" value="{{ old('crawled_at') }}">
                                <p class="muted" style="font-size:12px; margin:6px 0 0;">空なら保存時刻になる。</p>
                            </div>
                        </div>

                        <h3 class="subsection-title">基本状態</h3>
                        <div class="grid kv-grid">
                            <div class="field">
                                <label for="ssl_enabled">SSL</label>
                                <select id="ssl_enabled" name="ssl_enabled" required>
                                    @foreach ($hpObservationOptions['ssl_enabled'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('ssl_enabled', $triStateValue($latestHpFact?->ssl_enabled)) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="mobile_friendly">スマホ対応</label>
                                <select id="mobile_friendly" name="mobile_friendly" required>
                                    @foreach ($hpObservationOptions['mobile_friendly'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('mobile_friendly', $triStateValue($latestHpFact?->mobile_friendly)) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="update_status">全体更新状態</label>
                                <select id="update_status" name="update_status" required>
                                    @foreach ($hpObservationOptions['update_status'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('update_status', $latestHpFact?->update_status ?? 'unknown') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="contact_method_type">問い合わせ導線</label>
                                <select id="contact_method_type" name="contact_method_type" required>
                                    @foreach ($hpObservationOptions['contact_method_type'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('contact_method_type', $latestHpFact?->contact_method_type ?? 'unknown') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="cms_type">CMS / ビルダー推定</label>
                                <select id="cms_type" name="cms_type" required>
                                    @foreach ($hpObservationOptions['cms_type'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('cms_type', $latestHpFact?->cms_type ?? 'unknown') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="footer_year_status">フッター年</label>
                                <select id="footer_year_status" name="footer_year_status" required>
                                    @foreach ($hpObservationOptions['footer_year_status'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('footer_year_status', $latestHpFact?->footer_year_status ?? 'unknown') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="portal_dependency_level">SNS・ポータル依存</label>
                                <select id="portal_dependency_level" name="portal_dependency_level" required>
                                    @foreach ($hpObservationOptions['portal_dependency_level'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('portal_dependency_level', $latestHpFact?->portal_dependency_level ?? 'unknown') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <h3 class="subsection-title">補助シグナル</h3>
                        <div class="grid kv-grid">
                            @foreach ([
                                'has_contact_form' => '問い合わせフォーム',
                                'has_sns_link' => 'SNSリンク',
                                'has_portal_link' => 'ポータルリンク',
                                'has_reservation' => '予約機能',
                                'has_ec' => 'EC/決済っぽさ',
                                'has_recruiting' => '採用情報',
                            ] as $field => $label)
                                <div class="field">
                                    <label for="{{ $field }}">{{ $label }}</label>
                                    <select id="{{ $field }}" name="{{ $field }}" required>
                                        @foreach ($hpObservationOptions['tri_state'] as $value => $optionLabel)
                                            <option value="{{ $value }}" @selected(old($field, $triStateValue($latestHpFact?->{$field})) === $value)>{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>

                        <h3 class="subsection-title">更新対象</h3>
                        <p class="muted" style="margin-top:-4px;">全部埋めなくてOK。見たものだけ「存在する・止まっている」などを入れる。</p>
                        <div class="grid target-grid">
                            @foreach ($updateTargets as $target)
                                @php
                                    $snapshotTarget = $latestTargetsById->get($target->id);
                                    $evidence = $snapshotTarget?->evidence_json ?? [];
                                    $defaultStatus = 'unknown';
                                    if ($snapshotTarget) {
                                        if ($snapshotTarget->is_present === false) {
                                            $defaultStatus = 'not_present';
                                        } elseif ($snapshotTarget->is_present === true && $snapshotTarget->is_stopped === true) {
                                            $defaultStatus = 'present_stopped';
                                        } elseif ($snapshotTarget->is_present === true && $snapshotTarget->is_stopped === false) {
                                            $defaultStatus = 'present_active';
                                        }
                                    }
                                @endphp
                                <div class="target-card">
                                    <div class="target-name">{{ $target->name }}</div>
                                    <div class="field">
                                        <label for="target_{{ $target->id }}_status">状態</label>
                                        <select id="target_{{ $target->id }}_status" name="targets[{{ $target->id }}][status]">
                                            @foreach ($hpObservationOptions['target_status'] as $value => $label)
                                                <option value="{{ $value }}" @selected(old("targets.{$target->id}.status", $defaultStatus) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label for="target_{{ $target->id }}_last_update_date">最終更新日</label>
                                        <input id="target_{{ $target->id }}_last_update_date" name="targets[{{ $target->id }}][last_update_date]" type="date" value="{{ old("targets.{$target->id}.last_update_date", optional($snapshotTarget?->last_update_date)->format('Y-m-d')) }}">
                                    </div>
                                    <div class="field" style="margin-bottom:0;">
                                        <label for="target_{{ $target->id }}_note">メモ</label>
                                        <input id="target_{{ $target->id }}_note" name="targets[{{ $target->id }}][note]" type="text" value="{{ old("targets.{$target->id}.note", $evidence['note'] ?? '') }}" placeholder="例：施工事例が2021年で停止">
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="field" style="margin-top:18px;">
                            <label for="observation_note">観測メモ</label>
                            <textarea id="observation_note" name="observation_note" placeholder="全体所感。例：HPは古いが施工事例の型はある。SNSは動いている。">{{ old('observation_note', $latestHpSnapshot?->observation_note) }}</textarea>
                        </div>

                        <button class="button" type="submit">HP観測を保存</button>
                    </form>
                @endif
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
