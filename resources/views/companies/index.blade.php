@extends('layouts.app', ['title' => 'Companies | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content companies-index">
        <style>
            .companies-index .stack { display:grid; gap:18px; }
            .companies-index .hero {
                background: linear-gradient(135deg, #eff6ff 0%, #ffffff 48%, #f8fafc 100%);
                overflow:hidden;
                position:relative;
            }
            .companies-index .hero::after {
                content:"";
                position:absolute;
                width:260px;
                height:260px;
                right:-80px;
                bottom:-100px;
                background:radial-gradient(circle, rgba(37,99,235,.14), transparent 62%);
            }
            .companies-index .hero-inner { position:relative; z-index:1; }
            .companies-index .hero-title {
                margin:6px 0 0;
                font-size: clamp(34px, 5vw, 48px);
                line-height:1.05;
                letter-spacing:-.03em;
            }
            .companies-index .hero-text {
                margin:10px 0 0;
                color:#667085;
                max-width:760px;
            }
            .companies-index .stat-grid {
                display:grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap:14px;
                margin-top:20px;
            }
            .companies-index .stat-card {
                padding:18px;
                border:1px solid #e2e8f0;
                border-radius:18px;
                background:rgba(255,255,255,.82);
            }
            .companies-index .stat-label {
                color:#667085;
                font-size:12px;
                font-weight:800;
                letter-spacing:.04em;
                text-transform:uppercase;
            }
            .companies-index .stat-value {
                margin-top:8px;
                font-size:32px;
                font-weight:900;
                line-height:1;
                letter-spacing:-.03em;
            }
            .companies-index .filter-card {
                box-shadow:none;
                border-radius:20px;
                padding:20px;
                margin:0;
            }
            .companies-index .score-pill {
                display:inline-flex;
                align-items:center;
                gap:6px;
                padding:8px 10px;
                border-radius:999px;
                font-size:13px;
                font-weight:900;
                white-space:nowrap;
            }
            .companies-index .score-pill.opportunity { background:#dcfce7; color:#166534; }
            .companies-index .score-pill.risk { background:#fee2e2; color:#991b1b; }
            .companies-index .score-pill.empty { background:#eef2f7; color:#667085; }
            .companies-index .judgment {
                display:inline-flex;
                align-items:center;
                padding:8px 10px;
                border-radius:999px;
                font-size:12px;
                font-weight:900;
                white-space:nowrap;
                background:#eef2ff;
                color:#1d4ed8;
            }
            .companies-index .judgment.green { background:#dcfce7; color:#166534; }
            .companies-index .judgment.red { background:#fee2e2; color:#991b1b; }
            .companies-index .judgment.gray { background:#eef2f7; color:#475467; }
            .companies-index .company-name {
                font-weight:900;
                font-size:15px;
                margin-bottom:4px;
            }
            .companies-index .subtext {
                color:#667085;
                font-size:12px;
                line-height:1.55;
            }
            .companies-index td { vertical-align:middle; }
            .companies-index .tight { white-space:nowrap; }
        </style>

        <div class="stack">
            <section class="card hero">
                <div class="hero-inner">
                    <div class="row">
                        <div>
                            <p class="muted" style="margin:0;">Phase0-10 / 正規化企業マスタ</p>
                            <h1 class="hero-title">companies</h1>
                            <p class="hero-text">
                                source_recordから作成された企業・屋号を、地域・業種・4軸スコア・kill状態で俯瞰する一覧。
                                次の営業候補抽出の土台になる画面。
                            </p>
                        </div>
                        <a class="button light" href="{{ route('source-records.index') }}">source_recordsへ</a>
                    </div>

                    <div class="stat-grid">
                        <div class="stat-card">
                            <div class="stat-label">Total</div>
                            <div class="stat-value">{{ number_format($totalCount) }}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Active</div>
                            <div class="stat-value">{{ number_format($activeCount) }}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Killed</div>
                            <div class="stat-value">{{ number_format($killedCount) }}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Scored</div>
                            <div class="stat-value">{{ number_format($scoredCount) }}</div>
                        </div>
                    </div>
                </div>
            </section>

            @if (session('status'))
                <div class="status">{{ session('status') }}</div>
            @endif

            <section class="card filter-card">
                <form method="GET" action="{{ route('companies.index') }}">
                    <div class="grid">
                        <div class="field" style="margin-bottom:0;">
                            <label for="q">検索</label>
                            <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="会社名・法人番号・地域など">
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label for="industry_id">業種</label>
                            <select id="industry_id" name="industry_id">
                                <option value="">すべて</option>
                                @foreach ($industries as $industry)
                                    <option value="{{ $industry->id }}" @selected((string) request('industry_id') === (string) $industry->id)>
                                        {{ $industry->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label for="status">status</label>
                            <select id="status" name="status">
                                <option value="">すべて</option>
                                <option value="candidate" @selected(request('status') === 'candidate')>candidate</option>
                                <option value="confirmed" @selected(request('status') === 'confirmed')>confirmed</option>
                                <option value="merged" @selected(request('status') === 'merged')>merged</option>
                            </select>
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label for="kill_state">kill状態</label>
                            <select id="kill_state" name="kill_state">
                                <option value="">すべて</option>
                                <option value="active" @selected(request('kill_state') === 'active')>未killのみ</option>
                                <option value="killed" @selected(request('kill_state') === 'killed')>kill済みのみ</option>
                            </select>
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label for="score_state">採点状態</label>
                            <select id="score_state" name="score_state">
                                <option value="">すべて</option>
                                <option value="unscored" @selected(request('score_state') === 'unscored')>未採点</option>
                                <option value="partial" @selected(request('score_state') === 'partial')>一部採点</option>
                                <option value="fully_scored" @selected(request('score_state') === 'fully_scored')>4軸採点済み</option>
                                <option value="has_auto_suggestion" @selected(request('score_state') === 'has_auto_suggestion')>自動提案記録あり</option>
                                <option value="manual_adjusted" @selected(request('score_state') === 'manual_adjusted')>手動補正あり</option>
                                <option value="suggestion_as_is" @selected(request('score_state') === 'suggestion_as_is')>提案どおり</option>
                            </select>
                        </div>
                        <div class="field" style="margin-bottom:0; align-self:end;">
                            <button class="button" type="submit">絞り込み</button>
                            <a class="button light" href="{{ route('companies.index') }}">リセット</a>
                        </div>
                    </div>
                </form>
            </section>

            <section class="card">
                <div class="row" style="margin-bottom:16px;">
                    <div>
                        <h2 style="margin:0; font-size:26px;">company一覧</h2>
                        <p class="muted" style="margin:6px 0 0;">機会とリスクを分けて見る。単純な総合点にはしない。</p>
                    </div>
                    <span class="badge gray">表示 {{ $companies->count() }} / {{ number_format($companies->total()) }}</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>会社・屋号</th>
                            <th>状態</th>
                            <th>業種 / 地域</th>
                            <th>score</th>
                            <th>判定</th>
                            <th>assets</th>
                            <th>domain</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($companies as $company)
                            @php
                                $scores = $company->scores->keyBy('axis');
                                $hpWeakness = optional($scores->get('hp_weakness'))->value;
                                $selfUpdateFit = optional($scores->get('self_update_fit'))->value;
                                $devDifficulty = optional($scores->get('dev_difficulty'))->value;
                                $portalDependence = optional($scores->get('portal_dependence'))->value;

                                $scoredAxesCount = collect([$hpWeakness, $selfUpdateFit, $devDifficulty, $portalDependence])
                                    ->filter(fn ($value) => $value !== null)
                                    ->count();

                                $autoSuggestionCount = $scores
                                    ->filter(fn ($score) => $score->auto_suggested_value !== null)
                                    ->count();

                                $manualAdjustedCount = $scores
                                    ->filter(fn ($score) => $score->auto_suggested_value !== null && (int) $score->value !== (int) $score->auto_suggested_value)
                                    ->count();

                                $opportunityScore = ($hpWeakness ?? 0) + ($selfUpdateFit ?? 0);
                                $riskScore = ($devDifficulty ?? 0) + ($portalDependence ?? 0);

                                if ($scoredAxesCount < 4) {
                                    $judgment = '未採点あり';
                                    $judgmentClass = 'gray';
                                } elseif ($opportunityScore >= 7 && $riskScore <= 3) {
                                    $judgment = '高機会・低リスク';
                                    $judgmentClass = 'green';
                                } elseif ($opportunityScore >= 7 && $riskScore >= 7) {
                                    $judgment = '高機会・高リスク';
                                    $judgmentClass = '';
                                } elseif ($opportunityScore <= 3 && $riskScore >= 7) {
                                    $judgment = '低機会・高リスク';
                                    $judgmentClass = 'red';
                                } elseif ($opportunityScore <= 3 && $riskScore <= 3) {
                                    $judgment = '低機会・低リスク';
                                    $judgmentClass = 'gray';
                                } else {
                                    $judgment = '要確認';
                                    $judgmentClass = '';
                                }
                            @endphp

                            <tr>
                                <td class="tight">{{ $company->id }}</td>
                                <td>
                                    <div class="company-name">{{ $company->display_name }}</div>
                                    @if ($company->legal_name)
                                        <div class="subtext">{{ $company->legal_name }}</div>
                                    @endif
                                    @if ($company->corporate_number)
                                        <div class="subtext">法人番号：{{ $company->corporate_number }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div style="display:grid; gap:6px; justify-items:start;">
                                        <span class="badge gray">{{ $company->status }}</span>
                                        <span class="badge {{ $company->is_killed ? 'red' : 'green' }}">{{ $company->is_killed ? 'killed' : 'active' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div><strong>{{ $company->industry?->name ?? '-' }}</strong></div>
                                    <div class="subtext">
                                        {{ $company->municipality?->prefecture?->name ?? $company->pref ?? '-' }}
                                        /
                                        {{ $company->municipality?->name ?? $company->city ?? '-' }}
                                    </div>
                                </td>
                                <td>
                                    @if ($scoredAxesCount > 0)
                                        <div style="display:grid; gap:6px;">
                                            <span class="score-pill opportunity">機会 {{ $opportunityScore }} / 10</span>
                                            <span class="score-pill risk">リスク {{ $riskScore }} / 10</span>
                                            <div class="subtext">採点 {{ $scoredAxesCount }} / 4</div>
                                            @if ($autoSuggestionCount > 0)
                                                <div class="subtext">auto提案 {{ $autoSuggestionCount }} / 補正 {{ $manualAdjustedCount }}</div>
                                            @endif
                                        </div>
                                    @else
                                        <div style="display:grid; gap:6px;">
                                            <span class="score-pill empty">未採点</span>
                                            <div class="subtext">採点 0 / 4</div>
                                        </div>
                                    @endif
                                </td>
                                <td><span class="judgment {{ $judgmentClass }}">{{ $judgment }}</span></td>
                                <td class="tight">
                                    <div class="subtext">source：{{ $company->source_links_count }}</div>
                                    <div class="subtext">domain：{{ $company->domains_count }}</div>
                                    <div class="subtext">kill：{{ $company->kill_flags_count }}</div>
                                </td>
                                <td style="overflow-wrap:anywhere;">
                                    {{ $company->primaryDomain?->normalized_domain ?? '-' }}
                                </td>
                                <td class="tight"><a class="button small light" href="{{ route('companies.show', $company) }}">詳細</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="muted">まだcompaniesがない。</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="pagination" style="margin-top:18px;">
                    {{ $companies->links() }}
                </div>
            </section>
        </div>
    </main>
@endsection
