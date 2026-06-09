@extends('layouts.app', ['title' => 'Source Records | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="muted" style="margin:0;">Phase1 / 生データ取り込み・選別</p>
                    <h1 style="margin:6px 0 0;">source_records</h1>
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
                        <p class="muted">CSVのraw_industryを使う。</p>
                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <label for="link_status">状態</label>
                        <select id="link_status" name="link_status">
                            <option value="">すべて</option>
                            <option value="unlinked" @selected(request('link_status') === 'unlinked')>未リンク</option>
                            <option value="linked" @selected(request('link_status') === 'linked')>company化済み</option>
                        </select>
                        <p class="muted">company_source_linksの有無で判定。</p>
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
            @endphp

            <div class="card" style="box-shadow:none; padding:14px 18px; margin:0 0 16px; background:#f8fafc;">
                <div class="row">
                    <div>
                        <strong>処理キュー</strong>
                        <p class="muted" style="margin:6px 0 0;">現在の検索条件内で、未リンクsource_recordが {{ number_format($unlinkedQueueCount ?? 0) }} 件ある。専用ルートは追加せず、既存の一覧/詳細リンクだけで安全に処理する。</p>
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
                            <p class="muted" style="margin:6px 0 0;">チェックした未リンクsource_recordを、candidate companyとして一括作成する。既存companyへの自動リンクは誤統合防止のため行わない。</p>
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
                            <tr>
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
                {{ $sourceRecords->links() }}
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
