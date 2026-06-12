@extends('layouts.app', ['title' => '営業候補一覧 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content candidates-index">
        <style>
            .candidates-index .stack { display:grid; gap:20px; }
            .candidates-index .hero {
                background:
                    radial-gradient(circle at 88% 12%, rgba(22,163,74,.14), transparent 30%),
                    radial-gradient(circle at 8% 0%, rgba(31,94,255,.10), transparent 28%),
                    linear-gradient(135deg, rgba(255,255,255,.96), rgba(248,250,252,.92));
                position:relative;
                overflow:hidden;
            }
            .candidates-index .hero-inner { position:relative; z-index:1; }
            .candidates-index .stat-grid {
                display:grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap:14px;
                margin-top:22px;
            }
            .candidates-index .stat-card {
                padding:18px;
                border:1px solid #d9e2ee;
                border-radius:20px;
                background:rgba(255,255,255,.76);
                box-shadow:0 10px 26px rgba(16,24,40,.05);
            }
            .candidates-index .stat-label {
                color:#667085;
                font-size:12px;
                font-weight:900;
                letter-spacing:.08em;
                text-transform:uppercase;
            }
            .candidates-index .stat-value {
                margin-top:9px;
                font-size:32px;
                font-weight:950;
                line-height:1;
                letter-spacing:-.04em;
            }
            .candidates-index .control-card {
                padding:0;
                overflow:hidden;
                box-shadow:0 10px 26px rgba(16,24,40,.05);
            }
            .candidates-index .control-head {
                padding:18px 20px;
                border-bottom:1px solid #e4e7ec;
                background:rgba(248,250,252,.76);
            }
            .candidates-index .control-body { padding:20px; }
            .candidates-index .preset-tabs {
                display:flex;
                gap:10px;
                flex-wrap:wrap;
            }
            .candidates-index .tab {
                padding:10px 14px;
                border-radius:999px;
                text-decoration:none;
                border:1px solid #d9e2ee;
                background:rgba(255,255,255,.82);
                font-weight:900;
                color:#475467;
                box-shadow:0 8px 18px rgba(16,24,40,.04);
            }
            .candidates-index .tab.active {
                background:#0f172a;
                color:#fff;
                border-color:#0f172a;
                box-shadow:0 12px 24px rgba(15,23,42,.16);
            }
            .candidates-index .active-filters {
                display:flex;
                gap:8px;
                flex-wrap:wrap;
                margin-top:12px;
            }
            .candidates-index .filter-chip {
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
            .candidates-index .filter-chip span { color:#98a2b3; }
            .candidates-index .company-name {
                font-weight:950;
                font-size:15px;
                margin-bottom:4px;
            }
            .candidates-index .subtext {
                color:#667085;
                font-size:12px;
                line-height:1.55;
            }
            .candidates-index .score-pill {
                display:inline-flex;
                align-items:center;
                gap:6px;
                padding:8px 10px;
                border-radius:999px;
                font-size:13px;
                font-weight:900;
                white-space:nowrap;
            }
            .candidates-index .score-pill.opportunity { background:#dcfce7; color:#166534; }
            .candidates-index .score-pill.risk { background:#fee2e2; color:#991b1b; }
            .candidates-index .score-pill.priority { background:#dbeafe; color:#1d4ed8; }
            .candidates-index .score-pill.empty { background:#eef2f7; color:#667085; }
            .candidates-index .judgment {
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
            .candidates-index .judgment.green { background:#dcfce7; color:#166534; }
            .candidates-index .judgment.red { background:#fee2e2; color:#991b1b; }
            .candidates-index .judgment.gray { background:#eef2f7; color:#475467; }
            .candidates-index .domain-chip {
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
            .candidates-index td { vertical-align:middle; }
            .candidates-index .tight { white-space:nowrap; }
            .candidates-index .table-wrap table { min-width:1120px; }
            .candidates-index th a { text-decoration:none; color:inherit; }
            .candidates-index th a:hover { color:#1f5eff; }
        </style>

        @php
            $scoreStateLabels = [
                'unscored' => '未採点',
                'partial' => '一部採点',
                'fully_scored' => '4軸採点済み',
                'has_auto_suggestion' => '自動提案記録あり',
                'manual_adjusted' => '手動補正あり',
                'suggestion_as_is' => '提案どおり',
            ];
            $presetLabels = [
                'recommended' => '推奨',
                'high_opportunity' => '高機会',
                'needs_scoring' => '未採点あり',
                'all_active' => '全active',
            ];
            $activeFilterLinks = [];
            if (request('q')) {
                $activeFilterLinks[] = ['label' => '検索', 'value' => request('q'), 'url' => route('companies.candidates', request()->except(['page', 'q']))];
            }
            if (request('industry_id')) {
                $selectedIndustry = $industries->firstWhere('id', (int) request('industry_id'))?->name ?? request('industry_id');
                $activeFilterLinks[] = ['label' => '業種', 'value' => $selectedIndustry, 'url' => route('companies.candidates', request()->except(['page', 'industry_id']))];
            }
            if (request('status')) {
                $activeFilterLinks[] = ['label' => '状態', 'value' => request('status'), 'url' => route('companies.candidates', request()->except(['page', 'status']))];
            }
            if (request('score_state')) {
                $activeFilterLinks[] = ['label' => '採点', 'value' => $scoreStateLabels[request('score_state')] ?? request('score_state'), 'url' => route('companies.candidates', request()->except(['page', 'score_state']))];
            }
            if (request('pref')) {
                $activeFilterLinks[] = ['label' => '都道府県', 'value' => request('pref'), 'url' => route('companies.candidates', request()->except(['page', 'pref', 'city']))];
            }
            if (request('city')) {
                $activeFilterLinks[] = ['label' => '市区町村', 'value' => request('city'), 'url' => route('companies.candidates', request()->except(['page', 'city']))];
            }
        @endphp

        <div class="stack">
            <section class="card hero">
                <div class="hero-inner">
                    <div class="row">
                        <div>
                            <p class="page-kicker">Candidate Board</p>
                            <h1 class="page-title">営業候補一覧</h1>
                            <p class="page-subtitle">
                                未kill・未mergedのcompanyから、4軸スコアをもとに候補を抽出する営業前の選別ボード。
                            </p>
                            <details class="help-panel">
                                <summary>候補一覧の使い方</summary>
                                <div class="help-body">
                                    まずは「推奨：高機会・低リスク」を見る。まだ採点が足りない場合は「未採点あり」で採点待ちを処理し、候補精度を上げる。
                                </div>
                            </details>
                        </div>
                        <div class="actions">
                            <a class="button light" href="{{ route('dashboard') }}">Dashboard</a>
                            <a class="button light" href="{{ route('companies.index') }}">companies一覧へ</a>
                            <a class="button light" href="{{ route('source-records.index') }}">source_recordsへ</a>
                        </div>
                    </div>

                    <div class="stat-grid">
                        <div class="stat-card">
                            <div class="stat-label">Active base</div>
                            <div class="stat-value">{{ number_format($activeCandidateTotal) }}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Filtered</div>
                            <div class="stat-value">{{ number_format($filteredCount) }}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Preset</div>
                            <div class="stat-value" style="font-size:22px;">{{ $presetLabels[$preset] ?? $preset }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card control-card">
                <div class="control-head row">
                    <div>
                        <p class="section-label">Preset & Filter</p>
                        <h2 style="margin:4px 0 0; font-size:20px;">候補の絞り込み</h2>
                    </div>
                    <div class="actions">
                        <a class="button light small" href="{{ route('companies.candidates', ['preset' => $preset]) }}">条件クリア</a>
                    </div>
                </div>
                <div class="control-body">
                    <div class="preset-tabs">
                        <a class="tab {{ $preset === 'recommended' ? 'active' : '' }}" href="{{ route('companies.candidates', array_merge(request()->except('page'), ['preset' => 'recommended'])) }}">推奨：高機会・低リスク</a>
                        <a class="tab {{ $preset === 'high_opportunity' ? 'active' : '' }}" href="{{ route('companies.candidates', array_merge(request()->except('page'), ['preset' => 'high_opportunity'])) }}">高機会</a>
                        <a class="tab {{ $preset === 'needs_scoring' ? 'active' : '' }}" href="{{ route('companies.candidates', array_merge(request()->except('page'), ['preset' => 'needs_scoring'])) }}">未採点あり</a>
                        <a class="tab {{ $preset === 'all_active' ? 'active' : '' }}" href="{{ route('companies.candidates', array_merge(request()->except('page'), ['preset' => 'all_active'])) }}">全active</a>
                    </div>

                    <form method="GET" action="{{ route('companies.candidates') }}" style="margin-top:18px;">
                        <input type="hidden" name="preset" value="{{ $preset }}">
                        <input type="hidden" name="sort" value="{{ $sort ?? request('sort', 'priority') }}">
                        <input type="hidden" name="direction" value="{{ $direction ?? request('direction', 'desc') }}">
                        <div class="grid">
                            <div class="field" style="margin-bottom:0;">
                                <label for="q">検索</label>
                                <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="会社名・法人番号・domain・地域など">
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
                                <label for="status">状態</label>
                                <select id="status" name="status">
                                    <option value="">すべて</option>
                                    <option value="candidate" @selected(request('status') === 'candidate')>candidate</option>
                                    <option value="confirmed" @selected(request('status') === 'confirmed')>confirmed</option>
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:0;">
                                <label for="score_state">採点状態</label>
                                <select id="score_state" name="score_state">
                                    <option value="">すべて</option>
                                    @foreach ($scoreStateLabels as $value => $label)
                                        <option value="{{ $value }}" @selected(request('score_state') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:0;">
                                <label for="pref">都道府県</label>
                                <select id="pref" name="pref">
                                    <option value="">すべて</option>
                                    @foreach ($prefOptions as $pref)
                                        <option value="{{ $pref }}" @selected(request('pref') === $pref)>{{ $pref }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:0;">
                                <label for="city">市区町村</label>
                                <select id="city" name="city">
                                    <option value="">すべて</option>
                                    @foreach ($cityOptions as $city)
                                        <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:0; align-self:end;">
                                <button class="button" type="submit">絞り込み</button>
                                <a class="button light" href="{{ route('companies.candidates') }}">リセット</a>
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

            @php
                $sortKey = $sort ?? request('sort', 'priority');
                $sortDirection = $direction ?? request('direction', 'desc');
                $sortUrl = function (string $key) use ($sortKey, $sortDirection) {
                    $nextDirection = ($sortKey === $key && $sortDirection === 'asc') ? 'desc' : 'asc';
                    return route('companies.candidates', array_merge(request()->except(['page']), [
                        'sort' => $key,
                        'direction' => $nextDirection,
                    ]));
                };
                $sortMark = function (string $key) use ($sortKey, $sortDirection) {
                    if ($sortKey !== $key) {
                        return '';
                    }
                    return $sortDirection === 'asc' ? ' ↑' : ' ↓';
                };
            @endphp

            <section class="card">
                <div class="row" style="margin-bottom:16px;">
                    <div>
                        <p class="section-label">Candidate List</p>
                        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                            <h2 style="margin:0; font-size:26px;">候補リスト</h2>
                            <span class="badge gray">表示 {{ $companies->count() }} / {{ number_format($companies->total()) }}</span>
                        </div>
                    </div>
                    <details class="help-panel" style="min-width:min(440px, 100%); margin-top:0;">
                        <summary>並び替えとpriority</summary>
                        <div class="help-body">
                            priorityは並び替え用の内部スコア。ソート条件を変えた時に順位番号が混乱しないよう、順位列は表示しない。
                        </div>
                    </details>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th><a href="{{ $sortUrl('display_name') }}">会社・屋号{{ $sortMark('display_name') }}</a></th>
                            <th>
                                <a href="{{ $sortUrl('industry') }}">業種{{ $sortMark('industry') }}</a>
                                /
                                <a href="{{ $sortUrl('region') }}">地域{{ $sortMark('region') }}</a>
                            </th>
                            <th>
                                <a href="{{ $sortUrl('opportunity_score') }}">機会{{ $sortMark('opportunity_score') }}</a>
                                /
                                <a href="{{ $sortUrl('risk_score') }}">リスク{{ $sortMark('risk_score') }}</a>
                            </th>
                            <th>
                                <a href="{{ $sortUrl('scored_axes_count') }}">判定{{ $sortMark('scored_axes_count') }}</a>
                                <div class="subtext">
                                    <a href="{{ $sortUrl('auto_suggestion_count') }}">auto{{ $sortMark('auto_suggestion_count') }}</a>
                                    /
                                    <a href="{{ $sortUrl('manual_adjusted_count') }}">補正{{ $sortMark('manual_adjusted_count') }}</a>
                                </div>
                            </th>
                            <th><a href="{{ $sortUrl('priority') }}">priority{{ $sortMark('priority') }}</a></th>
                            <th>
                                <a href="{{ $sortUrl('source_links_count') }}">source{{ $sortMark('source_links_count') }}</a>
                                /
                                <a href="{{ $sortUrl('domains_count') }}">domain数{{ $sortMark('domains_count') }}</a>
                                /
                                <a href="{{ $sortUrl('kill_flags_count') }}">kill{{ $sortMark('kill_flags_count') }}</a>
                            </th>
                            <th><a href="{{ $sortUrl('domain') }}">domain{{ $sortMark('domain') }}</a></th>
                            <th>営業入り口</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($companies as $company)
                            <tr>
                                <td>
                                    <div class="company-name">{{ $company->display_name }}</div>
                                    @if ($company->legal_name)
                                        <div class="subtext">{{ $company->legal_name }}</div>
                                    @endif
                                    @if ($company->corporate_number)
                                        <div class="subtext">法人番号：{{ $company->corporate_number }}</div>
                                    @endif
                                    <div class="subtext">状態：{{ $company->status }}</div>
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
                                    @if ($company->scored_axes_count > 0)
                                        <div style="display:grid; gap:6px; justify-items:start;">
                                            <span class="score-pill opportunity">機会 {{ $company->opportunity_score }} / 10</span>
                                            <span class="score-pill risk">リスク {{ $company->risk_score }} / 10</span>
                                        </div>
                                    @else
                                        <span class="score-pill empty">未採点</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="judgment {{ $company->candidate_judgment_class }}">{{ $company->candidate_judgment }}</span>
                                    <div class="subtext" style="margin-top:6px;">採点 {{ $company->scored_axes_count }} / 4</div>
                                    <div class="subtext">auto提案 {{ $company->auto_suggestion_count }} / 補正 {{ $company->manual_adjusted_count }}</div>
                                </td>
                                <td>
                                    <span class="score-pill priority">{{ number_format($company->candidate_priority_score, 1) }}</span>
                                </td>
                                <td class="tight">
                                    <div class="subtext">source：{{ $company->source_links_count }}</div>
                                    <div class="subtext">domain：{{ $company->domains_count }}</div>
                                    <div class="subtext">kill：{{ $company->kill_flags_count }}</div>
                                </td>
                                <td>
                                    <span class="domain-chip">{{ $company->primaryDomain?->normalized_domain ?? '-' }}</span>
                                </td>
                                <td>
                                    @php $hpFact = $company->latestHpFact; @endphp
                                    @if (!$hpFact)
                                        <span style="font-size:11px;color:var(--muted);">—</span>
                                    @else
                                        <div style="display:flex;flex-direction:column;gap:3px;">
                                            @if ($hpFact->hp_contact_email)
                                                <span class="badge green">メール</span>
                                            @endif
                                            @if ($hpFact->hp_contact_form_url)
                                                <span class="badge blue">フォーム</span>
                                            @endif
                                            @if ($hpFact->hp_contact_phone)
                                                <span class="badge gray">電話</span>
                                            @endif
                                            @if (!$hpFact->hp_contact_email && !$hpFact->hp_contact_form_url && !$hpFact->hp_contact_phone)
                                                <span class="badge red">入口なし</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="tight">
                                    <a class="button small" href="{{ route('companies.show', $company) }}">詳細</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="empty-state">
                                    <div class="empty-state-box">
                                        <div class="empty-icon">候</div>
                                        <p class="empty-title">条件に合う営業候補がない</p>
                                        <p class="empty-copy">4軸スコアを増やすか、プリセットを「全active」に変えると候補の母集団を確認できる。</p>
                                        <div class="empty-actions">
                                            <a class="button small light" href="{{ route('companies.candidates', ['preset' => 'active']) }}">全activeを見る</a>
                                            <a class="button small" href="{{ route('companies.index', ['score_review' => 'unscored']) }}">未採点companyを見る</a>
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
