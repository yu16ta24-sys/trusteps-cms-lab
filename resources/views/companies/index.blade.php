@extends('layouts.app', ['title' => '企業マスタ | TRUSTEPS CMS Lab'])

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
            }
            .companies-index .table-wrap td, .companies-index .table-wrap th { padding:6px 14px; }
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
            /* compact pagination */
            .companies-index .pagination nav > div:first-child { display:none !important; }
            .companies-index .pagination nav > div:last-child {
                display:flex !important; align-items:center; justify-content:center; gap:8px; flex-wrap:nowrap;
            }
            .companies-index .pagination nav > div:last-child > div:first-child { display:none !important; }
            .companies-index .pagination nav > div:last-child > div:last-child { display:flex; }
            .companies-index .pagination span[aria-current] span,
            .companies-index .pagination a {
                display:inline-flex; align-items:center; justify-content:center;
                min-width:28px; height:28px; padding:0 6px;
                font-size:12px; font-weight:700; line-height:1;
                border:1px solid #d9e2ee; background:#fff; color:#344054;
                text-decoration:none; transition:background .15s;
            }
            .companies-index .pagination a:hover { background:#f1f5f9; color:#1f5eff; }
            .companies-index .pagination span[aria-current] span {
                background:#0f172a; color:#fff; border-color:#0f172a;
            }
            .companies-index .pagination span[aria-disabled] span {
                display:inline-flex; align-items:center; justify-content:center;
                min-width:28px; height:28px; padding:0 6px;
                font-size:12px; color:#c0cada; border:1px solid #e4e7ec; background:#f8fafc;
            }
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
            if (request('hp_state')) {
                $hpStateLabels = ['unanalyzed' => 'HP未解析', 'url_dead' => 'URL死亡'];
                $activeFilterLinks[] = ['label' => 'HP解析', 'value' => $hpStateLabels[request('hp_state')] ?? request('hp_state'), 'url' => route('companies.index', request()->except(['page', 'hp_state']))];
            }
            if (request('pref')) {
                $activeFilterLinks[] = ['label' => '都道府県', 'value' => request('pref'), 'url' => route('companies.index', request()->except(['page', 'pref', 'city']))];
            }
            if (request('city')) {
                $activeFilterLinks[] = ['label' => '市区町村', 'value' => request('city'), 'url' => route('companies.index', request()->except(['page', 'city']))];
            }
        @endphp

        <div class="stack">
            <section class="card hero">
                <div class="hero-inner">
                    <div class="row">
                        <div>
                            <p class="page-kicker">企業マスタ</p>
                            <h1 class="page-title">企業マスタ</h1>
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
                            <a class="button light" href="{{ route('dashboard') }}">ダッシュボード</a>
                            <a class="button light" href="{{ route('source-records.index') }}">HP未確認リストへ</a>
                            <form method="POST" action="{{ route('companies.recalculate-all') }}"
                                  onsubmit="return confirm('全{{ $totalCount }}件のスコアを再計算しますか？');">
                                @csrf
                                <button class="button light" type="submit">全社スコア再計算</button>
                            </form>
                            <button class="button light" onclick="openReanalyzeModal()">全社HP再解析</button>
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
                                    @php
                                        $parentIndustries = $industries->whereNull('parent_id');
                                        $childByParent    = $industries->whereNotNull('parent_id')->groupBy('parent_id');
                                    @endphp
                                    @foreach ($parentIndustries as $parent)
                                        @if ($childByParent->has($parent->id))
                                            <optgroup label="{{ $parent->name }}">
                                                @foreach ($childByParent[$parent->id] as $child)
                                                    <option value="{{ $child->id }}" @selected((string) request('industry_id') === (string) $child->id)>{{ $child->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @else
                                            <option value="{{ $parent->id }}" @selected((string) request('industry_id') === (string) $parent->id)>{{ $parent->name }}</option>
                                        @endif
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
                            <div class="field" style="margin-bottom:0;">
                                <label for="hp_state">HP解析</label>
                                <select id="hp_state" name="hp_state">
                                    <option value="">すべて</option>
                                    <option value="unanalyzed" @selected(request('hp_state') === 'unanalyzed')>未解析</option>
                                    <option value="url_dead" @selected(request('hp_state') === 'url_dead')>URL死亡</option>
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:0;">
                                <label for="pref">都道府県</label>
                                <select id="pref-select-index" name="pref">
                                    <option value="">すべて</option>
                                    @foreach ($prefectures as $pref)
                                        <option value="{{ $pref->name }}" @selected(request('pref') === $pref->name)>{{ $pref->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:0;">
                                <label for="city-select-index">市区町村</label>
                                <select id="city-select-index" name="city">
                                    <option value="">すべて</option>
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

            @if (request('hp_state') === 'url_dead')
            <div style="padding:14px 20px; background:#fef2f2; border:1px solid #fca5a5; border-radius:14px;">
                ⚠️ URL死亡のみ表示中 — HP URLが無効な企業です。Google検索で正しいURLを探してください。
            </div>
            @endif

            <section class="card">
                <div class="row" style="margin-bottom:16px;">
                    <div>
                        <p class="section-label">List</p>
                        <div class="table-title">
                            <h2 style="margin:0; font-size:26px;">企業一覧</h2>
                            <span class="badge gray">表示 {{ $companies->count() }} / {{ number_format($companies->total()) }}</span>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        @if (request('hp_state') === 'unanalyzed' && $companies->total() > 0)
                            <button class="button" onclick="openAnalyzeModal()" style="white-space:nowrap;">
                                {{ $companies->total() }}社を一括HP解析
                            </button>
                        @endif
                        <details class="help-panel" style="min-width:min(380px, 100%); margin-top:0;">
                            <summary>スコア表示の考え方</summary>
                            <div class="help-body">
                                機会とリスクは合算しない。高機会・高リスクと低機会・低リスクを混ぜないため、別々の軸として見る。
                            </div>
                        </details>
                    </div>
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
                            <th>HP</th>
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
                                <td>
                                    <div style="display:grid; gap:6px; justify-items:start;">
                                        @if (isset($deadDomainIdSet[$company->primary_domain_id]))
                                            <span style="background:#ef4444;color:#fff;border-radius:4px;padding:2px 8px;font-size:12px;">URL死亡</span>
                                        @endif
                                        <span class="judgment {{ $judgmentClass }}">{{ $judgment }}</span>
                                    </div>
                                </td>
                                <td class="tight">
                                    <div class="subtext">source：{{ $company->source_links_count }}</div>
                                    <div class="subtext">domain：{{ $company->domains_count }}</div>
                                    <div class="subtext">kill：{{ $company->kill_flags_count }}</div>
                                </td>
                                <td>
                                    @if ($company->primaryDomain?->normalized_domain)
                                        @php
                                            $hpHref = $company->primaryDomain->url
                                                ?? (str_starts_with($company->primaryDomain->normalized_domain, 'http')
                                                    ? $company->primaryDomain->normalized_domain
                                                    : 'https://' . $company->primaryDomain->normalized_domain);
                                        @endphp
                                        <a href="{{ $hpHref }}" target="_blank" class="domain-chip" style="color:#1d4ed8;text-decoration:none;">HP</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="tight" style="display:flex; gap:6px; align-items:center;">
                                    @php
                                        $googleQ = urlencode(($company->display_name ?? '') . ' ' . ($company->municipality?->prefecture?->name ?? $company->pref ?? ''));
                                    @endphp
                                    <a class="button small light" href="https://www.google.com/search?q={{ $googleQ }}" target="_blank" rel="noopener">検索</a>
                                    <a class="button small light" href="{{ route('companies.show', $company) }}">詳細</a>
                                </td>
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

    {{-- HP一括解析モーダル --}}
    <div id="analyze-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,.52); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:24px; padding:32px; width:min(480px,90vw); box-shadow:0 24px 64px rgba(0,0,0,.22);">
            <h3 style="margin:0 0 6px; font-size:20px; font-weight:950;">HP一括解析</h3>
            <p style="margin:0 0 20px; font-size:13px; color:#667085;">HP未解析の全社を順番に解析し、完了後にスコアを再計算します。</p>

            <div id="am-status" style="font-size:14px; font-weight:700; color:#344054; margin-bottom:10px;">解析を開始してください。</div>

            <div style="background:#e4e7ec; border-radius:999px; height:10px; overflow:hidden; margin-bottom:8px;">
                <div id="am-bar" style="height:100%; width:0%; background:#1f5eff; border-radius:999px; transition:width .4s ease;"></div>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:16px;">
                <span id="am-count" style="font-size:12px; color:#98a2b3;">— / —社完了</span>
                <span id="am-pct" style="font-size:12px; color:#98a2b3;">0%</span>
            </div>

            <div id="am-company" style="font-size:13px; color:#475467; min-height:20px; margin-bottom:20px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"></div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button id="am-close-btn" class="button light" onclick="closeAnalyzeModal()">キャンセル</button>
                <button id="am-start-btn" class="button" onclick="startAnalyze()">解析を開始</button>
            </div>
        </div>
    </div>

    <script>
    const INDEX_PREF_DATA = @json($prefectures->map(fn($p) => ['name' => $p->name, 'cities' => $p->municipalities->pluck('name')]));
    const INDEX_SELECTED_PREF = @json(request('pref', ''));
    const INDEX_SELECTED_CITY = @json(request('city', ''));
    </script>
    <script>
    (function () {
        const prefSelect = document.getElementById('pref-select-index');
        const citySelect = document.getElementById('city-select-index');

        function populateIndexCities(prefName, selectedCity) {
            citySelect.innerHTML = '<option value="">すべて</option>';
            const pref = INDEX_PREF_DATA.find(p => p.name === prefName);
            if (pref) {
                pref.cities.forEach(city => {
                    const opt = document.createElement('option');
                    opt.value = city;
                    opt.textContent = city;
                    if (city === selectedCity) opt.selected = true;
                    citySelect.appendChild(opt);
                });
            }
        }

        if (prefSelect) {
            prefSelect.addEventListener('change', function () {
                populateIndexCities(this.value, '');
            });
            if (INDEX_SELECTED_PREF) {
                populateIndexCities(INDEX_SELECTED_PREF, INDEX_SELECTED_CITY);
            }
        }
    })();
    </script>
    <script>
    (function () {
        let evtSource = null;

        window.openAnalyzeModal = function () {
            resetModal();
            document.getElementById('analyze-modal').style.display = 'flex';
        };

        window.closeAnalyzeModal = function () {
            if (evtSource) { evtSource.close(); evtSource = null; }
            document.getElementById('analyze-modal').style.display = 'none';
        };

        window.startAnalyze = function () {
            document.getElementById('am-start-btn').style.display = 'none';
            document.getElementById('am-close-btn').textContent = 'キャンセル';
            document.getElementById('am-status').textContent = '解析中...';

            evtSource = new EventSource('{{ route('companies.analyze-unanalyzed.stream') }}');

            evtSource.onmessage = function (e) {
                const d = JSON.parse(e.data);
                const pct = d.total > 0 ? Math.round(d.done / d.total * 100) : 0;

                document.getElementById('am-bar').style.width = pct + '%';
                document.getElementById('am-pct').textContent = pct + '%';
                document.getElementById('am-count').textContent = d.done + ' / ' + d.total + '社完了';

                if (d.company_name) {
                    document.getElementById('am-company').textContent = '処理中: ' + d.company_name;
                }

                if (d.finished) {
                    evtSource.close(); evtSource = null;
                    document.getElementById('am-status').textContent =
                        '完了！ 成功 ' + d.success_count + '社 / 失敗 ' + d.fail_count + '社（スコア再計算済み）';
                    document.getElementById('am-company').textContent = '';
                    document.getElementById('am-close-btn').textContent = '閉じる';
                }
            };

            evtSource.onerror = function () {
                if (evtSource) { evtSource.close(); evtSource = null; }
                document.getElementById('am-status').textContent = 'エラーが発生しました。ページをリロードして再試行してください。';
                document.getElementById('am-close-btn').textContent = '閉じる';
            };
        };

        function resetModal() {
            document.getElementById('am-bar').style.width = '0%';
            document.getElementById('am-pct').textContent = '0%';
            document.getElementById('am-count').textContent = '— / —社完了';
            document.getElementById('am-company').textContent = '';
            document.getElementById('am-status').textContent = '解析を開始してください。';
            document.getElementById('am-start-btn').style.display = '';
            document.getElementById('am-close-btn').textContent = 'キャンセル';
        }
    })();
    </script>

    {{-- 全社HP再解析モーダル --}}
    <div id="reanalyze-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,.52); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:24px; padding:32px; width:min(480px,90vw); box-shadow:0 24px 64px rgba(0,0,0,.22);">
            <h3 style="margin:0 0 6px; font-size:20px; font-weight:950;">全社HP再解析</h3>
            <p style="margin:0 0 20px; font-size:13px; color:#667085;">全社（primary domain保有・非merged）を順番に再解析し、完了後にスコアを再計算します。</p>

            <div id="ra-status" style="font-size:14px; font-weight:700; color:#344054; margin-bottom:10px;">解析を開始してください。</div>

            <div style="background:#e4e7ec; border-radius:999px; height:10px; overflow:hidden; margin-bottom:8px;">
                <div id="ra-bar" style="height:100%; width:0%; background:#1f5eff; border-radius:999px; transition:width .4s ease;"></div>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:16px;">
                <span id="ra-count" style="font-size:12px; color:#98a2b3;">— / —社完了</span>
                <span id="ra-pct" style="font-size:12px; color:#98a2b3;">0%</span>
            </div>

            <div id="ra-company" style="font-size:13px; color:#475467; min-height:20px; margin-bottom:20px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"></div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button id="ra-close-btn" class="button light" onclick="closeReanalyzeModal()">キャンセル</button>
                <button id="ra-start-btn" class="button" onclick="startReanalyze()">解析を開始</button>
            </div>
        </div>
    </div>

    <script>
    (function () {
        let raEvtSource = null;

        window.openReanalyzeModal = function () {
            resetReanalyzeModal();
            document.getElementById('reanalyze-modal').style.display = 'flex';
        };

        window.closeReanalyzeModal = function () {
            if (raEvtSource) { raEvtSource.close(); raEvtSource = null; }
            document.getElementById('reanalyze-modal').style.display = 'none';
        };

        window.startReanalyze = function () {
            document.getElementById('ra-start-btn').style.display = 'none';
            document.getElementById('ra-close-btn').textContent = 'キャンセル';
            document.getElementById('ra-status').textContent = '解析中...';

            raEvtSource = new EventSource('{{ route('companies.reanalyze-all.stream') }}');

            raEvtSource.onmessage = function (e) {
                const d = JSON.parse(e.data);
                const pct = d.total > 0 ? Math.round(d.done / d.total * 100) : 0;

                document.getElementById('ra-bar').style.width = pct + '%';
                document.getElementById('ra-pct').textContent = pct + '%';
                document.getElementById('ra-count').textContent = d.done + ' / ' + d.total + '社完了';

                if (d.company_name) {
                    document.getElementById('ra-company').textContent = '解析中: ' + d.done + '/' + d.total + '件（' + d.company_name + '）';
                }

                if (d.finished) {
                    raEvtSource.close(); raEvtSource = null;
                    document.getElementById('ra-status').textContent =
                        d.total + '件の解析が完了しました（成功 ' + d.success_count + '社 / 失敗 ' + d.fail_count + '社）';
                    document.getElementById('ra-company').textContent = '';
                    document.getElementById('ra-close-btn').textContent = '閉じる';
                }
            };

            raEvtSource.onerror = function () {
                if (raEvtSource) { raEvtSource.close(); raEvtSource = null; }
                document.getElementById('ra-status').textContent = 'エラーが発生しました。ページをリロードして再試行してください。';
                document.getElementById('ra-close-btn').textContent = '閉じる';
            };
        };

        function resetReanalyzeModal() {
            document.getElementById('ra-bar').style.width = '0%';
            document.getElementById('ra-pct').textContent = '0%';
            document.getElementById('ra-count').textContent = '— / —社完了';
            document.getElementById('ra-company').textContent = '';
            document.getElementById('ra-status').textContent = '解析を開始してください。';
            document.getElementById('ra-start-btn').style.display = '';
            document.getElementById('ra-close-btn').textContent = 'キャンセル';
        }
    })();
    </script>
@endsection
