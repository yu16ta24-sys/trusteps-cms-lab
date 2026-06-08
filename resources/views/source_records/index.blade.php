@extends('layouts.app', ['title' => 'Source Records | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="muted" style="margin:0;">Phase0-4 / 生データ取り込み</p>
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

            <p class="muted">総件数：{{ number_format($totalCount) }} 件</p>

            <form method="GET" action="{{ route('source-records.index') }}" class="card" style="box-shadow:none; padding:18px; margin:20px 0;">
                <div class="grid">
                    <div class="field" style="margin-bottom:0;">
                        <label for="q">検索</label>
                        <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="会社名・URL・都道府県・市区町村など">
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
                    <div class="field" style="margin-bottom:0; align-self:end;">
                        <button class="button" type="submit">絞り込み</button>
                        <a class="button light" href="{{ route('source-records.index') }}">リセット</a>
                    </div>
                </div>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>source_type</th>
                        <th>name_norm</th>
                        <th>domain</th>
                        <th>pref/city</th>
                        <th>fetched_at</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($sourceRecords as $record)
                        <tr>
                            <td>{{ $record->id }}</td>
                            <td>{{ $record->source_type }}</td>
                            <td>{{ $record->name_norm ?? '-' }}</td>
                            <td>
                                <div>{{ $record->normalized_domain ?? '-' }}</div>
                                @if ($record->source_url)
                                    <div class="muted" style="max-width:320px; overflow-wrap:anywhere;">{{ $record->source_url }}</div>
                                @endif
                            </td>
                            <td>{{ $record->pref ?? '-' }} / {{ $record->city ?? '-' }}</td>
                            <td>{{ optional($record->fetched_at)->format('Y-m-d H:i') ?? '-' }}</td>
                            <td>
                                <a class="button small light" href="{{ route('source-records.show', $record) }}">詳細</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">まだsource_recordsがない。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                {{ $sourceRecords->links() }}
            </div>
        </section>
    </main>
@endsection
