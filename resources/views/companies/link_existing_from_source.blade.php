@extends('layouts.app', ['title' => '既存companyへリンク | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="muted" style="margin:0;">Phase0-6 / 既存companyへの手動リンク</p>
                    <h1 style="margin:6px 0 0;">既存companyへリンク</h1>
                </div>
                <a class="button light" href="{{ route('source-records.show', $sourceRecord) }}">source_recordへ戻る</a>
            </div>

            @if ($errors->any())
                <div class="error" style="margin-top:20px;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="card" style="box-shadow:none; margin-top:20px;">
                <h2 style="margin-top:0;">リンク元source_record</h2>
                <div class="table-wrap">
                    <table>
                        <tbody>
                        <tr><th>source_record_id</th><td>{{ $sourceRecord->id }}</td></tr>
                        <tr><th>source_type</th><td>{{ $sourceRecord->source_type }}</td></tr>
                        <tr><th>name_norm</th><td>{{ $sourceRecord->name_norm ?? '-' }}</td></tr>
                        <tr><th>source_url</th><td style="overflow-wrap:anywhere;">{{ $sourceRecord->source_url ?? '-' }}</td></tr>
                        <tr><th>domain</th><td>{{ $sourceRecord->normalized_domain ?? '-' }}</td></tr>
                        <tr><th>pref/city</th><td>{{ $sourceRecord->pref ?? '-' }} / {{ $sourceRecord->city ?? '-' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="GET" action="{{ route('companies.link-existing-from-source', $sourceRecord) }}" class="card" style="box-shadow:none; padding:18px; margin:20px 0;">
                <div class="grid">
                    <div class="field" style="margin-bottom:0;">
                        <label for="q">既存company検索</label>
                        <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="会社名・法人番号・地域など">
                    </div>
                    <div class="field" style="margin-bottom:0; align-self:end;">
                        <button class="button" type="submit">検索</button>
                        <a class="button light" href="{{ route('companies.link-existing-from-source', $sourceRecord) }}">リセット</a>
                    </div>
                </div>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>会社・屋号</th>
                        <th>status</th>
                        <th>業種</th>
                        <th>地域</th>
                        <th>domain</th>
                        <th>リンク</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($companies as $company)
                        <tr>
                            <td>{{ $company->id }}</td>
                            <td>
                                <div><strong>{{ $company->display_name }}</strong></div>
                                @if ($company->legal_name)
                                    <div class="muted">{{ $company->legal_name }}</div>
                                @endif
                            </td>
                            <td><span class="badge gray">{{ $company->status }}</span></td>
                            <td>{{ $company->industry?->name ?? '-' }}</td>
                            <td>
                                {{ $company->municipality?->prefecture?->name ?? $company->pref ?? '-' }}
                                /
                                {{ $company->municipality?->name ?? $company->city ?? '-' }}
                            </td>
                            <td>{{ $company->primaryDomain?->normalized_domain ?? '-' }}</td>
                            <td>
                                <form method="POST" action="{{ route('companies.store-link-existing-from-source', $sourceRecord) }}" onsubmit="return confirm('このsource_recordを company #{{ $company->id }} にリンクする？');">
                                    @csrf
                                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                                    <input type="hidden" name="match_type" value="manual_same">
                                    <button class="button small" type="submit">このcompanyにリンク</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">companyが見つからない。先に新規company作成を使う。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                {{ $companies->links() }}
            </div>
        </section>
    </main>
@endsection
