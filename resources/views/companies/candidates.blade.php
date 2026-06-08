@extends('layouts.app', ['title' => '営業候補一覧 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content candidates-index">
        <style>
            .candidates-index .stack { display:grid; gap:18px; }
            .candidates-index .hero {
                background: linear-gradient(135deg, #ecfdf5 0%, #ffffff 45%, #eff6ff 100%);
                position:relative;
                overflow:hidden;
            }
            .candidates-index .hero::after {
                content:"";
                position:absolute;
                width:280px;
                height:280px;
                right:-90px;
                bottom:-110px;
                background:radial-gradient(circle, rgba(22,163,74,.14), transparent 62%);
            }
            .candidates-index .hero-inner { position:relative; z-index:1; }
            .candidates-index .hero-title {
                margin:6px 0 0;
                font-size:clamp(34px, 5vw, 48px);
                line-height:1.05;
                letter-spacing:-.03em;
            }
            .candidates-index .hero-text {
                margin:10px 0 0;
                color:#667085;
                max-width:760px;
            }
            .candidates-index .stat-grid {
                display:grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap:14px;
                margin-top:20px;
            }
            .candidates-index .stat-card {
                padding:18px;
                border:1px solid #e2e8f0;
                border-radius:18px;
                background:rgba(255,255,255,.82);
            }
            .candidates-index .stat-label {
                color:#667085;
                font-size:12px;
                font-weight:800;
                letter-spacing:.04em;
                text-transform:uppercase;
            }
            .candidates-index .stat-value {
                margin-top:8px;
                font-size:32px;
                font-weight:900;
                line-height:1;
                letter-spacing:-.03em;
            }
            .candidates-index .preset-tabs {
                display:flex;
                gap:10px;
                flex-wrap:wrap;
            }
            .candidates-index .tab {
                padding:10px 14px;
                border-radius:999px;
                text-decoration:none;
                border:1px solid #e2e8f0;
                background:#fff;
                font-weight:800;
                color:#475467;
            }
            .candidates-index .tab.active {
                background:#111827;
                color:#fff;
                border-color:#111827;
            }
            .candidates-index .rank {
                width:42px;
                height:42px;
                border-radius:14px;
                display:inline-flex;
                align-items:center;
                justify-content:center;
                background:#111827;
                color:#fff;
                font-weight:900;
            }
            .candidates-index .company-name {
                font-weight:900;
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
            .candidates-index td { vertical-align:middle; }
            .candidates-index .tight { white-space:nowrap; }
        </style>

        <div class="stack">
            <section class="card hero">
                <div class="hero-inner">
                    <div class="row">
                        <div>
                            <p class="muted" style="margin:0;">Phase0-11 / 営業候補抽出</p>
                            <h1 class="hero-title">営業候補一覧</h1>
                            <p class="hero-text">
                                未kill・未mergedのcompanyから、4軸スコアをもとに候補を抽出する。
                                まずは「高機会・低リスク」を優先して見る。
                            </p>
                        </div>
                        <div class="actions">
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
                            <div class="stat-value" style="font-size:22px;">{{ $preset }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card" style="box-shadow:none;">
                <div class="row">
                    <div class="preset-tabs">
                        <a class="tab {{ $preset === 'recommended' ? 'active' : '' }}" href="{{ route('companies.candidates', array_merge(request()->except('page'), ['preset' => 'recommended'])) }}">推奨：高機会・低リスク</a>
                        <a class="tab {{ $preset === 'high_opportunity' ? 'active' : '' }}" href="{{ route('companies.candidates', array_merge(request()->except('page'), ['preset' => 'high_opportunity'])) }}">高機会</a>
                        <a class="tab {{ $preset === 'needs_scoring' ? 'active' : '' }}" href="{{ route('companies.candidates', array_merge(request()->except('page'), ['preset' => 'needs_scoring'])) }}">未採点あり</a>
                        <a class="tab {{ $preset === 'all_active' ? 'active' : '' }}" href="{{ route('companies.candidates', array_merge(request()->except('page'), ['preset' => 'all_active'])) }}">全active</a>
                    </div>
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
                                @foreach ($industries as $industry)
                                    <option value="{{ $industry->id }}" @selected((string) request('industry_id') === (string) $industry->id)>
                                        {{ $industry->name }}
                                    </option>
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
                        <h2 style="margin:0; font-size:26px;">候補リスト</h2>
                        <p class="muted" style="margin:6px 0 0;">
                            優先度 = 機会スコアを強めに、リスクを減点して並べる簡易指標。絶対値ではなく並び替え用。
                        </p>
                    </div>
                    <span class="badge gray">表示 {{ $companies->count() }} / {{ number_format($companies->total()) }}</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>順位</th>
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
                            <th><a href="{{ $sortUrl('scored_axes_count') }}">判定{{ $sortMark('scored_axes_count') }}</a></th>
                            <th><a href="{{ $sortUrl('priority') }}">priority{{ $sortMark('priority') }}</a></th>
                            <th>
                                <a href="{{ $sortUrl('source_links_count') }}">source{{ $sortMark('source_links_count') }}</a>
                                /
                                <a href="{{ $sortUrl('domains_count') }}">domain数{{ $sortMark('domains_count') }}</a>
                                /
                                <a href="{{ $sortUrl('kill_flags_count') }}">kill{{ $sortMark('kill_flags_count') }}</a>
                            </th>
                            <th><a href="{{ $sortUrl('domain') }}">domain{{ $sortMark('domain') }}</a></th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($companies as $company)
                            <tr>
                                <td class="tight">
                                    <span class="rank">{{ ($companies->currentPage() - 1) * $companies->perPage() + $loop->iteration }}</span>
                                </td>
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
                                        <div style="display:grid; gap:6px;">
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
                                </td>
                                <td>
                                    <span class="score-pill priority">{{ number_format($company->candidate_priority_score, 1) }}</span>
                                </td>
                                <td class="tight">
                                    <div class="subtext">source：{{ $company->source_links_count }}</div>
                                    <div class="subtext">domain：{{ $company->domains_count }}</div>
                                    <div class="subtext">kill：{{ $company->kill_flags_count }}</div>
                                </td>
                                <td style="overflow-wrap:anywhere;">
                                    {{ $company->primaryDomain?->normalized_domain ?? '-' }}
                                </td>
                                <td class="tight">
                                    <a class="button small" href="{{ route('companies.show', $company) }}">詳細</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="muted">
                                    条件に合う営業候補がまだない。companyに4軸スコアを入れるか、プリセットを「全active」に変えて確認。
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
