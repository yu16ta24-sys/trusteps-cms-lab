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
                position:relative; overflow:hidden;
            }
            .candidates-index .hero-inner { position:relative; z-index:1; }
            .candidates-index .stat-grid {
                display:grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap:14px; margin-top:22px;
            }
            .candidates-index .stat-card {
                padding:18px; border:1px solid #d9e2ee; border-radius:20px;
                background:rgba(255,255,255,.76); box-shadow:0 10px 26px rgba(16,24,40,.05);
            }
            .candidates-index .stat-label {
                color:#667085; font-size:12px; font-weight:900;
                letter-spacing:.08em; text-transform:uppercase;
            }
            .candidates-index .stat-value {
                margin-top:9px; font-size:32px; font-weight:950;
                line-height:1; letter-spacing:-.04em;
            }
            .candidates-index .control-card { padding:0; overflow:hidden; box-shadow:0 10px 26px rgba(16,24,40,.05); }
            .candidates-index .control-head { padding:18px 20px; border-bottom:1px solid #e4e7ec; background:rgba(248,250,252,.76); }
            .candidates-index .control-body { padding:20px; }
            .candidates-index .preset-tabs { display:flex; gap:10px; flex-wrap:wrap; }
            .candidates-index .tab {
                padding:10px 14px; border-radius:999px; text-decoration:none;
                border:1px solid #d9e2ee; background:rgba(255,255,255,.82);
                font-weight:900; color:#475467; box-shadow:0 8px 18px rgba(16,24,40,.04);
            }
            .candidates-index .tab.active {
                background:#0f172a; color:#fff; border-color:#0f172a;
                box-shadow:0 12px 24px rgba(15,23,42,.16);
            }
            .candidates-index .active-filters { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
            .candidates-index .filter-chip {
                display:inline-flex; align-items:center; gap:7px;
                padding:7px 10px; border-radius:999px; border:1px solid #d9e2ee;
                background:#fff; color:#344054; font-size:12px; font-weight:900; text-decoration:none;
            }
            .candidates-index .filter-chip span { color:#98a2b3; }
            .candidates-index .company-name { font-weight:950; font-size:15px; margin-bottom:4px; }
            .candidates-index .subtext { color:#667085; font-size:12px; line-height:1.55; }
            .candidates-index .domain-chip {
                display:inline-flex; max-width:220px; padding:7px 10px;
                border-radius:12px; background:#f8fafc; border:1px solid #e4e7ec;
                color:#344054; font-size:12px; font-weight:800; overflow-wrap:anywhere;
            }
            .candidates-index td { vertical-align:middle; }
            .candidates-index .tight { white-space:nowrap; }
            .candidates-index .table-wrap table { min-width:900px; }
            .candidates-index th a { text-decoration:none; color:inherit; }
            .candidates-index th a:hover { color:#1f5eff; }
            /* モバイル用 Previous/Next div を非表示 */
            .candidates-index .pagination nav > div:first-child { display:none !important; }
            /* デスクトップ用コンテナを1行フレックスに */
            .candidates-index .pagination nav > div:last-child {
                display:flex !important; align-items:center; justify-content:center; gap:8px; flex-wrap:nowrap;
            }
            /* "Showing X to Y of Z results" テキストを非表示 */
            .candidates-index .pagination nav > div:last-child > div:first-child { display:none !important; }
            /* ページボタン群 */
            .candidates-index .pagination nav > div:last-child > div:last-child { display:flex; }
            .candidates-index .pagination span[aria-current] span,
            .candidates-index .pagination a {
                display:inline-flex; align-items:center; justify-content:center;
                min-width:28px; height:28px; padding:0 6px;
                font-size:12px; font-weight:700; line-height:1;
                border:1px solid #d9e2ee; background:#fff; color:#344054;
                text-decoration:none; transition:background .15s;
            }
            .candidates-index .pagination a:hover { background:#f1f5f9; color:#1f5eff; }
            .candidates-index .pagination span[aria-current] span {
                background:#0f172a; color:#fff; border-color:#0f172a;
            }
            .candidates-index .pagination span[aria-disabled] span {
                display:inline-flex; align-items:center; justify-content:center;
                min-width:28px; height:28px; padding:0 6px;
                font-size:12px; color:#c0cada; border:1px solid #e4e7ec; background:#f8fafc;
            }
        </style>

        @php
            $v2TypeLabels = [
                'renewal_candidate'        => 'HPリニューアル',
                'cms_conversion_candidate' => 'CMS化',
                'maintenance_candidate'    => '保守・更新',
                'new_site_candidate'       => '新規制作',
                'reject'                   => '優先度低',
                'unclassified'             => '未分類',
            ];
            $v2RankColors = ['A' => 'green', 'B' => 'blue', 'C' => 'amber', 'D' => 'red'];
            $presetLabels = [
                'all'          => '全候補',
                'rank_a'       => 'Aランク',
                'rank_b'       => 'Bランク',
                'manual'       => '手動候補',
                'unclassified' => '未分類',
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
            if (request('rank')) {
                $activeFilterLinks[] = ['label' => 'ランク', 'value' => request('rank'), 'url' => route('companies.candidates', request()->except(['page', 'rank']))];
            }
            if (request('candidate_type')) {
                $activeFilterLinks[] = ['label' => '提案タイプ', 'value' => $v2TypeLabels[request('candidate_type')] ?? request('candidate_type'), 'url' => route('companies.candidates', request()->except(['page', 'candidate_type']))];
            }
            if (request('pref')) {
                $activeFilterLinks[] = ['label' => '都道府県', 'value' => request('pref'), 'url' => route('companies.candidates', request()->except(['page', 'pref', 'city']))];
            }
            if (request('city')) {
                $activeFilterLinks[] = ['label' => '市区町村', 'value' => request('city'), 'url' => route('companies.candidates', request()->except(['page', 'city']))];
            }
            if (request('manual_only')) {
                $activeFilterLinks[] = ['label' => '手動候補', 'value' => 'のみ', 'url' => route('companies.candidates', request()->except(['page', 'manual_only']))];
            }
        @endphp

        <div class="stack">
            {{-- ヒーロー --}}
            <section class="card hero">
                <div class="hero-inner">
                    <div class="row">
                        <div>
                            <p class="page-kicker">Candidate Board</p>
                            <h1 class="page-title">営業候補一覧</h1>
                            <p class="page-subtitle">5軸スコアをもとに営業候補を選別するボード。</p>
                        </div>
                        <div class="actions">
                            <a class="button light" href="{{ route('dashboard') }}">Dashboard</a>
                            <a class="button light" href="{{ route('companies.index') }}">companies一覧</a>
                        </div>
                    </div>

                    <div class="stat-grid">
                        <div class="stat-card">
                            <div class="stat-label">Active Base</div>
                            <div class="stat-value">{{ number_format($activeCandidateTotal) }}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Aランク</div>
                            <div class="stat-value" style="color:#16a34a;">{{ number_format($rankACount) }}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Bランク</div>
                            <div class="stat-value" style="color:#1d4ed8;">{{ number_format($rankBCount) }}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">手動候補</div>
                            <div class="stat-value" style="color:#b45309;">{{ number_format($manualCount) }}</div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- フィルターコントロール --}}
            <section class="card control-card">
                <div class="control-head row">
                    <div>
                        <p class="section-label">Preset & Filter</p>
                        <h2 style="margin:4px 0 0; font-size:20px;">候補の絞り込み</h2>
                    </div>
                    <div class="actions">
                        <a class="button light small" href="{{ route('companies.candidates') }}">全クリア</a>
                    </div>
                </div>
                <div class="control-body">
                    <div class="preset-tabs">
                        @foreach ($presetLabels as $key => $label)
                            <a class="tab {{ $preset === $key ? 'active' : '' }}"
                               href="{{ route('companies.candidates', array_merge(request()->except(['page', 'preset']), ['preset' => $key])) }}">{{ $label }}</a>
                        @endforeach
                    </div>

                    <form method="GET" action="{{ route('companies.candidates') }}" style="margin-top:18px;">
                        <input type="hidden" name="preset" value="{{ $preset }}">
                        <input type="hidden" name="sort" value="{{ $sort }}">
                        <input type="hidden" name="direction" value="{{ $direction }}">
                        <div class="grid">
                            <div class="field" style="margin-bottom:0;">
                                <label for="q">検索</label>
                                <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="会社名・domain・地域など">
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
                                <label for="rank">ランク</label>
                                <select id="rank" name="rank">
                                    <option value="">すべて</option>
                                    @foreach (['A', 'B', 'C', 'D'] as $r)
                                        <option value="{{ $r }}" @selected($selectedRank === $r)>{{ $r }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:0;">
                                <label for="candidate_type">提案タイプ</label>
                                <select id="candidate_type" name="candidate_type">
                                    <option value="">すべて</option>
                                    @foreach ($v2TypeLabels as $typeKey => $typeLabel)
                                        <option value="{{ $typeKey }}" @selected($selectedCandidateType === $typeKey)>{{ $typeLabel }}</option>
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
                                <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; white-space:nowrap;">
                                    <input type="checkbox" name="manual_only" value="1" @checked(request('manual_only'))>
                                    手動候補のみ
                                </label>
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
                $sortUrl = function (string $key) use ($sort, $direction) {
                    $nextDir = ($sort === $key && $direction === 'asc') ? 'desc' : 'asc';
                    return route('companies.candidates', array_merge(request()->except(['page']), ['sort' => $key, 'direction' => $nextDir]));
                };
                $sortMark = function (string $key) use ($sort, $direction) {
                    return $sort === $key ? ($direction === 'asc' ? ' ↑' : ' ↓') : '';
                };
            @endphp

            {{-- 候補リスト --}}
            <section class="card">
                <div class="row" style="margin-bottom:16px;">
                    <div>
                        <p class="section-label">Candidate List</p>
                        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                            <h2 style="margin:0; font-size:26px;">候補リスト</h2>
                            <span class="badge gray">表示 {{ $companies->count() }} / {{ number_format($companies->total()) }}</span>
                        </div>
                    </div>
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
                                <a href="{{ $sortUrl('v2_rank') }}">ランク{{ $sortMark('v2_rank') }}</a>
                                /
                                <a href="{{ $sortUrl('v2_total_score') }}">スコア{{ $sortMark('v2_total_score') }}</a>
                            </th>
                            <th><a href="{{ $sortUrl('v2_candidate_type') }}">提案タイプ{{ $sortMark('v2_candidate_type') }}</a></th>
                            <th>信頼度</th>
                            <th>手動</th>
                            <th><a href="{{ $sortUrl('domain') }}">domain{{ $sortMark('domain') }}</a></th>
                            <th>営業入り口</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($companies as $company)
                            @php
                                $outreachPhase = $company->latestOutreachContact?->phase;
                                $outreachColors = ['list'=>'gray','attacked'=>'blue','negotiating'=>'amber','contracted'=>'green','rejected'=>'red','hold'=>'gray'];
                                $outreachLabels = ['list'=>'未着手','attacked'=>'アタック済','negotiating'=>'商談中','contracted'=>'成約','rejected'=>'見送り','hold'=>'保留'];
                                $v2RankTd = $company->v2_rank;
                            @endphp
                            <tr>
                                {{-- 会社・屋号 --}}
                                <td>
                                    <div class="company-name">{{ $company->display_name }}</div>
                                    @if ($company->legal_name)
                                        <div class="subtext">{{ $company->legal_name }}</div>
                                    @endif
                                    <div class="subtext">{{ $company->status }}</div>
                                    @if ($outreachPhase)
                                        <span class="badge {{ $outreachColors[$outreachPhase] ?? 'gray' }}" style="margin-top:4px;">{{ $outreachLabels[$outreachPhase] ?? $outreachPhase }}</span>
                                    @endif
                                </td>
                                {{-- 業種/地域 --}}
                                <td>
                                    <div><strong>{{ $company->industry?->name ?? '-' }}</strong></div>
                                    <div class="subtext">
                                        {{ $company->municipality?->prefecture?->name ?? $company->pref ?? '-' }}
                                        /
                                        {{ $company->municipality?->name ?? $company->city ?? '-' }}
                                    </div>
                                </td>
                                {{-- ランク/スコア --}}
                                <td>
                                    @if ($v2RankTd)
                                        <span class="badge {{ $v2RankColors[$v2RankTd] ?? 'gray' }}" style="font-size:13px;font-weight:700;">
                                            {{ $v2RankTd }} &nbsp;{{ number_format((float)$company->v2_total_score, 2) }}
                                        </span>
                                    @else
                                        <span style="font-size:12px;color:var(--muted);">未計算</span>
                                    @endif
                                </td>
                                {{-- 提案タイプ --}}
                                <td>
                                    @if ($company->v2_candidate_type)
                                        <span class="badge gray" style="font-size:11px;">{{ $v2TypeLabels[$company->v2_candidate_type] ?? $company->v2_candidate_type }}</span>
                                    @else
                                        <span style="font-size:12px;color:var(--muted);">—</span>
                                    @endif
                                </td>
                                {{-- 信頼度 --}}
                                <td class="tight">
                                    @if ($company->v2_confidence !== null)
                                        @if ($company->v2_confidence < 0.70)
                                            <span class="badge amber" style="font-size:10px;display:block;margin-bottom:3px;">目視推奨</span>
                                        @endif
                                        <span style="font-size:13px;font-weight:700;">{{ (int)round($company->v2_confidence * 100) }}%</span>
                                    @else
                                        <span style="font-size:12px;color:var(--muted);">—</span>
                                    @endif
                                </td>
                                {{-- 手動候補バッジ --}}
                                <td class="tight">
                                    @if ($company->is_manual_candidate)
                                        <span class="badge amber" style="font-size:11px;">手動</span>
                                    @endif
                                </td>
                                {{-- DOMAIN --}}
                                <td>
                                    <span class="domain-chip">{{ $company->primaryDomain?->normalized_domain ?? '-' }}</span>
                                </td>
                                {{-- 営業入り口 --}}
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
                                {{-- 詳細 --}}
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
                                        <p class="empty-copy">フィルターを変更するか、プリセットを「全候補」に変えると母集団を確認できます。</p>
                                        <div class="empty-actions">
                                            <a class="button small light" href="{{ route('companies.candidates', ['preset' => 'all']) }}">全候補を見る</a>
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
