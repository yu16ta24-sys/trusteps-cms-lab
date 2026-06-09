@extends('layouts.app', ['title' => 'Companies | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content companies-index">
        <style>
            .companies-index .stack { display:grid; gap:20px; }
            .companies-index .hero {
                background:
                    radial-gradient(circle at 92% 18%, rgba(31, 94, 255, .12), transparent 28%),
                    linear-gradient(135deg, rgba(255,255,255,.96), rgba(248,250,252,.92));
                overflow:hidden;
                position:relative;
            }
            .companies-index .hero-inner { position:relative; z-index:1; }
            .companies-index .stat-grid {
                display:grid;
                grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
                gap:14px;
                margin-top:22px;
            }
            .companies-index .stat-card {
                padding:18px;
                border:1px solid #d9e2ee;
                border-radius:20px;
                background:rgba(255,255,255,.76);
                box-shadow:0 10px 26px rgba(16,24,40,.05);
            }
            .companies-index .stat-label {
                color:#667085;
                font-size:12px;
                font-weight:900;
                letter-spacing:.08em;
                text-transform:uppercase;
            }
            .companies-index .stat-value {
                margin-top:9px;
                font-size:32px;
                font-weight:950;
                line-height:1;
                letter-spacing:-.04em;
            }
            .companies-index .filter-shell {
                box-shadow:0 10px 26px rgba(16,24,40,.05);
                padding:0;
                overflow:hidden;
            }
            .companies-index .filter-head {
                padding:18px 20px;
                border-bottom:1px solid #e4e7ec;
                background:rgba(248,250,252,.76);
            }
            .companies-index .filter-body { padding:20px; }
            .companies-index .active-filters {
                display:flex;
                gap:8px;
                flex-wrap:wrap;
                margin-top:12px;
            }
            .companies-index .filter-chip {
                display:inline-flex;
                align-items:center;
                gap:7px;
                padding:7px 10px;
                border-radius:999px;
                border:1px solid #d9e2ee;
                background:#fff;
                color:#344054;
                font-size:12px;
                font-weight:900;
                text-decoration:none;
            }
            .companies-index .filter-chip span { color:#98a2b3; }
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
                font-weight:950;
                font-size:15px;
                margin-bottom:4px;
                letter-spacing:-.01em;
            }
            .companies-index .subtext {
                color:#667085;
                font-size:12px;
                line-height:1.55;
            }
            .companies-index .table-title {
                display:flex;
                gap:12px;
                align-items:center;
                flex-wrap:wrap;
            }
            .companies-index .domain-chip {
                display:inline-flex;
                max-width:220px;
                padding:7px 10px;
                border-radius:12px;
                background:#f8fafc;
                border:1px solid #e4e7ec;
                color:#344054;
                font-size:12px;
                font-weight:800;
                overflow-wrap:anywhere;
            }
            .companies-index td { vertical-align:middle; }
            .companies-index .tight { white-space:nowrap; }
            .companies-index .table-wrap table { min-width:1040px; }
        </style>

        @php
            $activeFilterLinks = [];
            if (request('q')) {
                $activeFilterLinks[] = ['label' => '検索', 'value' => request('q'), 'url' => route('companies.index', request()->except(['page', 'q']))];
            }
            if (request('industry_id')) {
                $selectedIndustry = $industries->firstWhere('id', (int) request('industry_id'))?->name ?? request('industry_id');
                $activeFilterLinks[] = ['label' => '業種', 'value' => $selectedIndustry, 'url' => route('companies.index', request()->except(['page', 'industry_id']))];
            }
            if (request('status')) {
                $activeFilterLinks[] = ['label' => 'status', 'value' => request('status'), 'url' => route('companies.index', request()->except(['page', 'status']))];
            }
            if (request('kill_state')) {
                $activeFilterLinks[] = ['label' => 'kill', 'value' => request('kill_state'), 'url' => route('companies.index', request()->except(['page', 'kill_state']))];
            }
            if (request('score_state')) {
                $scoreLabels = [
                    'unscored' => '未採点',
                    'partial' => '一部採点',
                    'fully_scored' => '4軸採点済み',
                    'has_auto_suggestion' => '自動提案記録あり',
                    'manual_adjusted' => '手動補正あり',
                    'suggestion_as_is' => '提案どおり',
                ];
                $activeFilterLinks[] = ['label' => '採点', 'value' => $scoreLabels[request('score_state')] ?? request('score_state'), 'url' => route('companies.index', request()->except(['page', 'score_state']))];
            }
        @endphp

        <div class="stack">
            <section class="card hero">
                <div class="hero-inner">
                    <div class="row">
                        <div>
                            <p class="page-kicker">Company Master</p>
                            <h1 class="page-title">companies</h1>
                            <p class="page-subtitle">
                                source_recordから作成された企業・屋号を、地域・業種・4軸スコア・kill状態で俯瞰する正規化マスタ。
                            </p>
                            <details class="help-panel">
                                <summary>この画面の見方</summary>
                                <div class="help-body">
                                    ここは営業送信リストではなく、事業者マスタの整理画面。まずは未採点・一部採点を減らし、候補一覧で比較できる状態に整える。
                                </div>
                            </details>
                        </div>
                        <div class="actions">
                            <a class="button light" href="{{ route('dashboard') }}">Dashboard</a>
                            <a class="button light" href="{{ route('source-records.index') }}">source_recordsへ</a>
                        </div>
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

            <section class="card filter-shell">
                <div class="filter-head row">
                    <div>
                        <p class="section-label">Filter</p>
                        <h2 style="margin:4px 0 0; font-size:20px;">絞り込み</h2>
                    </div>
                    <div class="actions">
                        <a class="button light small" href="{{ route('companies.index') }}">全条件クリア</a>
                    </div>
                </div>
                <div class="filter-body">
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

                    @if (count($activeFilterLinks) > 0)
                        <div class="active-filters">
                            @foreach ($activeFilterLinks as $filter)
                                <a class="filter-chip" href="{{ $filter['url'] }}">
                                    <span>{{ $filter['label'] }}</span>{{ $filter['value'] }} ×
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="card">
                <div class="row" style="margin-bottom:16px;">
                    <div>
                        <p class="section-label">List</p>
                        <div class="table-title">
                            <h2 style="margin:0; font-size:26px;">company一覧</h2>
                            <span class="badge gray">表示 {{ $companies->count() }} / {{ number_format($companies->total()) }}</span>
                        </div>
                    </div>
                    <details class="help-panel" style="min-width:min(420px, 100%); margin-top:0;">
                        <summary>スコア表示の考え方</summary>
                        <div class="help-body">
                            機会とリスクは合算しない。高機会・高リスクと低機会・低リスクを混ぜないため、別々の軸として見る。
                        </div>
                    </details>
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
                                <td class="tight"><span class="badge gray">#{{ $company->id }}</span></td>
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
                                        <div style="display:grid; gap:6px; justify-items:start;">
                                            <span class="score-pill opportunity">機会 {{ $opportunityScore }} / 10</span>
                                            <span class="score-pill risk">リスク {{ $riskScore }} / 10</span>
                                            <div class="subtext">採点 {{ $scoredAxesCount }} / 4</div>
                                            @if ($autoSuggestionCount > 0)
                                                <div class="subtext">auto提案 {{ $autoSuggestionCount }} / 補正 {{ $manualAdjustedCount }}</div>
                                            @endif
                                        </div>
                                    @else
                                        <div style="display:grid; gap:6px; justify-items:start;">
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
                                <td>
                                    <span class="domain-chip">{{ $company->primaryDomain?->normalized_domain ?? '-' }}</span>
                                </td>
                                <td class="tight"><a class="button small light" href="{{ route('companies.show', $company) }}">詳細</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="empty-state">
                                    <div class="empty-state-box">
                                        <div class="empty-icon">CO</div>
                                        <p class="empty-title">条件に合うcompanyがない</p>
                                        <p class="empty-copy">source_recordをcompany化するか、絞り込み条件をクリアして全体を確認。</p>
                                        <div class="empty-actions">
                                            <a class="button small light" href="{{ route('companies.index') }}">条件クリア</a>
                                            <a class="button small" href="{{ route('source-records.index', ['link_status' => 'unlinked']) }}">未リンクsourceを見る</a>
                                        </div>
                                    </div>
                                </td>
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
