@extends('layouts.app', ['title' => 'source_record詳細 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="muted" style="margin:0;">Source Record #{{ $sourceRecord->id }}</p>
                    <h1 style="margin:6px 0 0;">source_record 詳細</h1>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('source-records.index') }}">一覧へ戻る</a>
                    @if ($sourceRecord->sourceLink)
                        <a class="button" href="{{ route('companies.show', $sourceRecord->sourceLink->company) }}">リンク済みcompanyを見る</a>
                    @else
                        <a class="button" href="{{ route('companies.create-from-source', $sourceRecord) }}">新規company作成</a>
                        <a class="button light" href="{{ route('companies.link-existing-from-source', $sourceRecord) }}">既存companyへリンク</a>
                    @endif
                </div>
            </div>

            @if (session('status'))
                <div class="status" style="margin-top:20px;">{{ session('status') }}</div>
            @endif

            @if ($sourceRecord->sourceLink)
                <div class="status" style="margin-top:20px;">
                    このsource_recordは company #{{ $sourceRecord->sourceLink->company_id }} にリンク済み。
                    match_type：{{ $sourceRecord->sourceLink->match_type }}
                </div>
            @endif

            <div class="table-wrap" style="margin-top:24px;">
                <table>
                    <tbody>
                    <tr><th>ID</th><td>{{ $sourceRecord->id }}</td></tr>
                    <tr><th>source_type</th><td>{{ $sourceRecord->source_type }}</td></tr>
                    <tr><th>source_url</th><td style="overflow-wrap:anywhere;">{{ $sourceRecord->source_url ?? '-' }}</td></tr>
                    <tr><th>corporate_number</th><td>{{ $sourceRecord->corporate_number ?? '-' }}</td></tr>
                    <tr><th>normalized_domain</th><td>{{ $sourceRecord->normalized_domain ?? '-' }}</td></tr>
                    <tr><th>normalized_phone</th><td>{{ $sourceRecord->normalized_phone ?? '-' }}</td></tr>
                    <tr><th>name_norm</th><td>{{ $sourceRecord->name_norm ?? '-' }}</td></tr>
                    <tr><th>pref/city</th><td>{{ $sourceRecord->pref ?? '-' }} / {{ $sourceRecord->city ?? '-' }}</td></tr>
                    <tr><th>fetched_at</th><td>{{ optional($sourceRecord->fetched_at)->format('Y-m-d H:i:s') ?? '-' }}</td></tr>
                    <tr><th>created_at</th><td>{{ optional($sourceRecord->created_at)->format('Y-m-d H:i:s') ?? '-' }}</td></tr>
                    </tbody>
                </table>
            </div>

            <h2>raw_json</h2>
            <pre>{{ json_encode($sourceRecord->raw_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}</pre>
        </section>
    </main>
@endsection
