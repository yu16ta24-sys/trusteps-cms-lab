@extends('layouts.app', ['title' => 'company詳細 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="muted" style="margin:0;">Company #{{ $company->id }}</p>
                    <h1 style="margin:6px 0 0;">{{ $company->display_name }}</h1>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('companies.index') }}">companies一覧へ</a>
                    <a class="button light" href="{{ route('source-records.index') }}">source_recordsへ</a>
                </div>
            </div>

            @if (session('status'))
                <div class="status" style="margin-top:20px;">{{ session('status') }}</div>
            @endif

            <div class="table-wrap" style="margin-top:24px;">
                <table>
                    <tbody>
                    <tr><th>ID</th><td>{{ $company->id }}</td></tr>
                    <tr><th>status</th><td><span class="badge gray">{{ $company->status }}</span></td></tr>
                    <tr><th>display_name</th><td>{{ $company->display_name }}</td></tr>
                    <tr><th>legal_name</th><td>{{ $company->legal_name ?? '-' }}</td></tr>
                    <tr><th>name_norm</th><td>{{ $company->name_norm ?? '-' }}</td></tr>
                    <tr><th>industry</th><td>{{ $company->industry?->name ?? '-' }}</td></tr>
                    <tr><th>municipality</th><td>{{ $company->municipality?->prefecture?->name ?? $company->pref ?? '-' }} / {{ $company->municipality?->name ?? $company->city ?? '-' }}</td></tr>
                    <tr><th>corporate_number</th><td>{{ $company->corporate_number ?? '-' }}</td></tr>
                    <tr><th>primary_domain</th><td>{{ $company->primaryDomain?->url ?? '-' }} / {{ $company->primaryDomain?->normalized_domain ?? '-' }}</td></tr>
                    <tr><th>is_killed</th><td>{{ $company->is_killed ? 'true' : 'false' }}</td></tr>
                    <tr><th>created_at</th><td>{{ optional($company->created_at)->format('Y-m-d H:i:s') ?? '-' }}</td></tr>
                    </tbody>
                </table>
            </div>

            <h2>domains</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>url</th>
                        <th>normalized_domain</th>
                        <th>role</th>
                        <th>primary</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($company->domains as $domain)
                        <tr>
                            <td>{{ $domain->id }}</td>
                            <td style="overflow-wrap:anywhere;">{{ $domain->url }}</td>
                            <td>{{ $domain->normalized_domain ?? '-' }}</td>
                            <td>{{ $domain->role }}</td>
                            <td>{{ $domain->is_primary ? 'true' : 'false' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">domainなし</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <h2>source links</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>source_record_id</th>
                        <th>match_type</th>
                        <th>source_type</th>
                        <th>domain</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($company->sourceLinks as $link)
                        <tr>
                            <td>{{ $link->source_record_id }}</td>
                            <td>{{ $link->match_type }}</td>
                            <td>{{ $link->sourceRecord?->source_type ?? '-' }}</td>
                            <td>{{ $link->sourceRecord?->normalized_domain ?? '-' }}</td>
                            <td>
                                @if ($link->sourceRecord)
                                    <a class="button small light" href="{{ route('source-records.show', $link->sourceRecord) }}">sourceを見る</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">source linkなし</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
@endsection
