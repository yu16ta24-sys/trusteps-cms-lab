@extends('layouts.app', ['title' => 'Source Records | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">Phase1 / Intake</p>
                    <h1 class="page-title">source_records</h1>
                    <p class="page-subtitle">外部から取った生データを整理して、company化する入口。</p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('source-records.import') }}">CSV取り込み</a>
                    <a class="button" href="{{ route('source-records.create') }}">手動登録</a>
                </div>
            </div>

            @if (session('status'))
                <div class="status" style="margin-top:20px;">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="error" style="margin-top:20px;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <p class="muted">総件数：{{ number_format($totalCount) }} 件</p>

            <form method="GET" action="{{ route('source-records.index') }}" class="card" style="box-shadow:none; padding:18px; margin:20px 0;">
                <div class="grid">
                    <div class="field" style="margin-bottom:0;">
                        <label for="q">語句検索</label>
                        <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="会社名・URL・法人番号・domainなど">
                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <label for="source_type">source_type</label>
                        <select id="source_type" name="source_type">
                            <option value="">すべて</option>
                            @foreach ($sourceTypes as $type)
                                <option value="{{ $type }}" @selected(request('source_type') === $type)>{{ $type }}</option>
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
                    <div class="field" style="margin-bottom:0;">
                        <label for="raw_industry">業種</label>
                        <select id="raw_industry" name="raw_industry">
                            <option value="">すべて</option>
                            @foreach ($rawIndustryOptions as $industry)
                                <option value="{{ $industry }}" @selected(request('raw_industry') === $industry)>{{ $industry }}</option>
                            @endforeach
                        </select>

                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <label for="link_status">状態</label>
                        <select id="link_status" name="link_status">
                            <option value="">すべて</option>
                            <option value="unlinked" @selected(request('link_status') === 'unlinked')>未リンク</option>
                            <option value="linked" @selected(request('link_status') === 'linked')>company化済み</option>
                        </select>

                    </div>
                    <div class="field" style="margin-bottom:0; align-self:end;">
                        <button class="button" type="submit">絞り込み</button>
                        <a class="button light" href="{{ route('source-records.index') }}">リセット</a>
                    </div>
                </div>
            </form>

            @php
                $sortKey = $sort ?? request('sort', 'id');
                $sortDirection = $direction ?? request('direction', 'desc');
                $sortUrl = function (string $key) use ($sortKey, $sortDirection) {
                    $nextDirection = ($sortKey === $key && $sortDirection === 'asc') ? 'desc' : 'asc';
                    return route('source-records.index', array_merge(request()->except(['page']), [
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

                $currentPageUnlinked = $sourceRecords->getCollection()->filter(function ($record) {
                    return ! $record->sourceLink;
                });
                $currentPageLinkedCount = $sourceRecords->getCollection()->count() - $currentPageUnlinked->count();
                $firstUnlinkedId = optional($currentPageUnlinked->first())->id;
                $activeFilterItems = collect([
                    ['key' => 'q', 'label' => '語句', 'value' => request('q')],
                    ['key' => 'source_type', 'label' => 'source_type', 'value' => request('source_type')],
                    ['key' => 'pref', 'label' => '都道府県', 'value' => request('pref')],
                    ['key' => 'city', 'label' => '市区町村', 'value' => request('city')],
                    ['key' => 'raw_industry', 'label' => '業種', 'value' => request('raw_industry')],
                    ['key' => 'link_status', 'label' => '状態', 'value' => request('link_status') === 'unlinked' ? '未リンク' : (request('link_status') === 'linked' ? 'company化済み' : null)],
                ])->filter(function ($item) {
                    return $item['value'] !== null && $item['value'] !== '';
                })->values();

                $filterRemoveUrl = function (string $key) {
                    return route('source-records.index', request()->except(['page', $key]));
                };
                $unlinkedOnlyUrl = route('source-records.index', array_merge(request()->except(['page']), ['link_status' => 'unlinked']));
                $clearStatusUrl = route('source-records.index', request()->except(['page', 'link_status']));
                $clearLocationUrl = route('source-records.index', request()->except(['page', 'pref', 'city']));
                        @endphp

            <div class="card" style="box-shadow:none; padding:14px 18px; margin:0 0 16px; background:#fff7ed; border:1px solid #fed7aa;">
                <div class="row">
                    <div>
                        <strong>作業セッション</strong>
                        <p class="muted" style="margin:6px 0 0;">
                            現在の一覧：{{ number_format($sourceRecords->total()) }}件 / このページ：{{ number_format($sourceRecords->count()) }}件。
                            このページの未リンク：{{ number_format($currentPageUnlinked->count()) }}件 / company化済み：{{ number_format($currentPageLinkedCount) }}件。
                        </p>
                        @if ($activeFilterItems->isNotEmpty())
                            <div class="muted" style="margin:8px 0 0;">
                                絞り込み：
                                @foreach ($activeFilterItems as $item)
                                    <a class="badge gray" style="text-decoration:none;" href="{{ $filterRemoveUrl($item['key']) }}" title="この条件だけ解除">
                                        {{ $item['label'] }}：{{ $item['value'] }} ×
                                    </a>
                                @endforeach
                            </div>
                            <details class="help-panel"><summary>絞り込みバッジの使い方</summary><div class="help-body">各バッジを押すと、その条件だけ解除できる。作業範囲を崩さずに少しずつ絞り込みを調整するための機能。</div></details>
                        @else
                            <details class="help-panel"><summary>最初の絞り込み方</summary><div class="help-body">絞り込みなし。まずは都道府県・市区町村・業種で作業範囲を切るのがおすすめ。</div></details>
                        @endif
                    </div>
                    <div class="actions">
                        @if ($firstUnlinkedId)
                            <a class="button" href="{{ route('source-records.show', $firstUnlinkedId) }}">このページの先頭未リンク #{{ $firstUnlinkedId }}</a>
                        @else
                            <span class="button light" style="opacity:.55; cursor:not-allowed;">このページは未リンクなし</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card" style="box-shadow:none; padding:14px 18px; margin:0 0 16px; background:#f8fafc; border:1px solid #e2e8f0;">
                <div class="row">
                    <div>
                        <strong>フィルター操作</strong>
                        <details class="help-panel"><summary>フィルター操作とは</summary><div class="help-body">今の絞り込みを保ったまま、未リンクだけ表示したり、状態・地域だけを解除できる。</div></details>
                    </div>
                    <div class="actions">
                        <a class="button light" href="{{ $unlinkedOnlyUrl }}">この条件で未リンクだけ</a>
                        @if (request('link_status'))
                            <a class="button light" href="{{ $clearStatusUrl }}">状態だけ解除</a>
                        @endif
                        @if (request('pref') || request('city'))
                            <a class="button light" href="{{ $clearLocationUrl }}">地域だけ解除</a>
                        @endif
                        <a class="button light" href="{{ route('source-records.index') }}">全条件クリア</a>
                    </div>
                </div>
            </div>

            <div class="card" style="box-shadow:none; padding:14px 18px; margin:0 0 16px; background:#f8fafc;">
                <div class="row">
                    <div>
                        <strong>処理キュー</strong>
                        <p class="muted" style="margin:6px 0 0;">未リンク {{ number_format($unlinkedQueueCount ?? 0) }} 件</p>
                            <details class="help-panel"><summary>処理キューの考え方</summary><div class="help-body">現在の検索条件内で未リンクsource_recordを順番に処理する。専用ルートは追加せず、既存の一覧/詳細リンクだけで安全に動かす。</div></details>
                    </div>
                    <div class="actions">
                        <a class="button light" href="{{ route('source-records.index', array_merge(request()->except(['page']), ['link_status' => 'unlinked'])) }}">未リンクだけ表示</a>
                        @if ($nextUnlinkedSourceRecord)
                            <a class="button" href="{{ route('source-records.show', $nextUnlinkedSourceRecord) }}">先頭の未リンクを開く</a>
                        @else
                            <span class="button light" style="opacity:.55; cursor:not-allowed;">未リンクなし</span>
                        @endif
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('source-records.bulk-create-companies') }}">
                @csrf
                @foreach (request()->except(['source_record_ids', '_token']) as $key => $value)
                    @if (is_scalar($value) && $value !== null && $value !== '')
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach

                <div class="card" style="box-shadow:none; padding:14px 18px; margin:0 0 16px; background:#f8fafc;">
                    <div class="row">
                        <div>
                            <strong>一括操作</strong>
                            <details class="help-panel"><summary>一括company化の注意</summary><div class="help-body">チェックした未リンクsource_recordをcandidate companyとして一括作成する。既存companyへの自動リンクは誤統合防止のため行わない。</div></details>
                        </div>
                        <div class="actions">
                            <button class="button" type="submit" onclick="return confirm('チェックした未リンクsource_recordを一括company化する？リンク済みはスキップされる。');">選択分を一括company化</button>
                        </div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th><input type="checkbox" id="check-all-source-records" aria-label="全選択"></th>
                            <th><a href="{{ $sortUrl('id') }}">ID{{ $sortMark('id') }}</a></th>
                            <th><a href="{{ $sortUrl('source_type') }}">source_type{{ $sortMark('source_type') }}</a></th>
                            <th><a href="{{ $sortUrl('name_norm') }}">name_norm{{ $sortMark('name_norm') }}</a></th>
                            <th>業種</th>
                            <th><a href="{{ $sortUrl('normalized_domain') }}">domain{{ $sortMark('normalized_domain') }}</a></th>
                            <th><a href="{{ $sortUrl('pref_city') }}">pref/city{{ $sortMark('pref_city') }}</a></th>
                            <th><a href="{{ $sortUrl('fetched_at') }}">fetched_at{{ $sortMark('fetched_at') }}</a></th>
                            <th>状態</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($sourceRecords as $record)
                            @php
                                $isLinked = (bool) $record->sourceLink;
                            @endphp
                            <tr @if (! $isLinked && $record->id === ($firstUnlinkedId ?? null)) style="background:#fffbeb;" @endif>
                                <td>
                                    <input
                                        type="checkbox"
                                        class="source-record-check"
                                        name="source_record_ids[]"
                                        value="{{ $record->id }}"
                                        @disabled($isLinked)
                                        aria-label="source_record #{{ $record->id }}を選択"
                                    >
                                </td>
                                <td>{{ $record->id }}</td>
                                <td>{{ $record->source_type }}</td>
                                <td>{{ $record->name_norm ?? '-' }}</td>
                                <td>{{ data_get($record->raw_json, 'canonical.raw_industry') ?: data_get($record->raw_json, 'raw_industry', '-') }}</td>
                                <td>
                                    <div>{{ $record->normalized_domain ?? '-' }}</div>
                                    @if ($record->source_url)
                                        <div class="muted" style="max-width:320px; overflow-wrap:anywhere;">{{ $record->source_url }}</div>
                                    @endif
                                </td>
                                <td>{{ $record->pref ?? '-' }} / {{ $record->city ?? '-' }}</td>
                                <td>{{ optional($record->fetched_at)->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>
                                    @if ($isLinked)
                                        <span class="badge green">company化済み</span>
                                    @else
                                        <span class="badge gray">未リンク</span>
                                        @if ($record->id === ($firstUnlinkedId ?? null))
                                            <span class="badge" style="background:#f97316; color:#fff;">次に処理</span>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    <a class="button small light" href="{{ route('source-records.show', $record) }}">詳細</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="muted">条件に一致するsource_recordsがない。</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </form>

            <div class="pagination">
                {{ $sourceRecords->appends(request()->query())->links() }}
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkAll = document.getElementById('check-all-source-records');
            if (!checkAll) return;

            checkAll.addEventListener('change', function () {
                document.querySelectorAll('.source-record-check:not(:disabled)').forEach(function (checkbox) {
                    checkbox.checked = checkAll.checked;
                });
            });
        });
    </script>
@endsection
