@extends('layouts.app', ['title' => 'source_record詳細 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">source record / intake review</p>
                    <h1 class="page-title">source_record #{{ $sourceRecord->id }}</h1>
                    <p class="page-subtitle">
                        外部取得データの原典確認画面。ここでは生データを壊さず、company化するか既存companyへリンクするかだけを判断する。
                    </p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('source-records.index') }}">一覧へ戻る</a>
                    @if ($sourceRecord->sourceLink)
                        <a class="button" href="{{ route('companies.show', $sourceRecord->sourceLink->company) }}">リンク済みcompany</a>
                    @else
                        <a class="button" href="{{ route('companies.create-from-source', $sourceRecord) }}">新規company作成</a>
                        <a class="button light" href="{{ route('companies.link-existing-from-source', $sourceRecord) }}">既存companyへリンク</a>
                    @endif
                </div>
            </div>

            @if (session('status'))
                <div class="status" style="margin-top:20px;">{{ session('status') }}</div>
            @endif

            <details class="help-panel">
                <summary>この画面の判断ポイント</summary>
                <div class="help-body">
                    <div>同じ会社かどうかは、ドメイン・法人番号・名称・地域を見て判断する。住所や電話だけの一致で強引に統合しない。</div>
                    <div>迷う場合は新規company化せず、既存company検索で近い候補を確認する。誤統合より重複の方が後から直しやすい。</div>
                </div>
            </details>

            @if ($sourceRecord->sourceLink)
                <div class="info-strip" style="margin-top:20px; background:#ecfdf3; border-color:#bbf7d0;">
                    <div class="row">
                        <div>
                            <strong>リンク済み</strong>
                            <div class="muted" style="margin-top:4px;">
                                company #{{ $sourceRecord->sourceLink->company_id }} / match_type：{{ $sourceRecord->sourceLink->match_type }}
                            </div>
                        </div>
                        <a class="button light" href="{{ route('companies.show', $sourceRecord->sourceLink->company) }}">companyを開く</a>
                    </div>
                </div>
            @else
                <div class="info-strip" style="margin-top:20px; background:#fff7ed; border-color:#fed7aa;">
                    <div class="row">
                        <div>
                            <strong>処理待ちsource_record</strong>
                            <div class="muted" style="margin-top:4px;">
                                まだcompanyへリンクされていない。残り未リンク：{{ number_format($remainingUnlinkedCount ?? 0) }}件。
                            </div>
                        </div>
                        <div class="actions">
                            <a class="button" href="{{ route('companies.create-from-source', $sourceRecord) }}">新規company作成</a>
                            <a class="button light" href="{{ route('companies.link-existing-from-source', $sourceRecord) }}">既存companyへリンク</a>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid" style="margin-top:20px;">
                <div class="mini-card">
                    <div class="muted">name_norm</div>
                    <strong>{{ $sourceRecord->name_norm ?? '-' }}</strong>
                </div>
                <div class="mini-card">
                    <div class="muted">domain</div>
                    <strong>{{ $sourceRecord->normalized_domain ?? '-' }}</strong>
                </div>
                <div class="mini-card">
                    <div class="muted">地域</div>
                    <strong>{{ $sourceRecord->pref ?? '-' }} / {{ $sourceRecord->city ?? '-' }}</strong>
                </div>
                <div class="mini-card">
                    <div class="muted">取得日</div>
                    <strong>{{ optional($sourceRecord->fetched_at)->format('Y-m-d') ?? '-' }}</strong>
                </div>
            </div>

            <div class="info-strip" style="margin-top:20px;">
                <div class="row">
                    <div>
                        <p class="section-label">processing navigation</p>
                        <strong>未リンクsource_recordを前後に移動</strong>
                        <div class="muted" style="margin-top:4px;">新規ルートは使わず、既存の詳細リンクだけで移動する。</div>
                    </div>
                    <div class="actions">
                        @if ($previousUnlinkedSourceRecord)
                            <a class="button light" href="{{ route('source-records.show', $previousUnlinkedSourceRecord) }}">前の未リンク #{{ $previousUnlinkedSourceRecord->id }}</a>
                        @else
                            <span class="button light" style="opacity:.55; cursor:not-allowed;">前の未リンクなし</span>
                        @endif

                        @if ($nextUnlinkedSourceRecord)
                            <a class="button" href="{{ route('source-records.show', $nextUnlinkedSourceRecord) }}">次の未リンク #{{ $nextUnlinkedSourceRecord->id }}</a>
                        @else
                            <span class="button light" style="opacity:.55; cursor:not-allowed;">次の未リンクなし</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="table-wrap" style="margin-top:24px;">
                <table>
                    <tbody>
                    <tr><th>ID</th><td>{{ $sourceRecord->id }}</td></tr>
                    <tr><th>source_type</th><td><span class="badge gray">{{ $sourceRecord->source_type }}</span></td></tr>
                    <tr><th>source_url</th><td style="overflow-wrap:anywhere;">{{ $sourceRecord->source_url ?? '-' }}</td></tr>
                    <tr><th>corporate_number</th><td>{{ $sourceRecord->corporate_number ?? '-' }}</td></tr>
                    <tr><th>normalized_domain</th><td>{{ $sourceRecord->normalized_domain ?? '-' }}</td></tr>
                    <tr><th>normalized_phone</th><td>{{ $sourceRecord->normalized_phone ?? '-' }}</td></tr>
                    <tr><th>name_norm</th><td>{{ $sourceRecord->name_norm ?? '-' }}</td></tr>
                    <tr><th>raw_industry</th><td>{{ data_get($sourceRecord->raw_json, 'raw_industry', '-') }}</td></tr>
                    <tr><th>pref/city</th><td>{{ $sourceRecord->pref ?? '-' }} / {{ $sourceRecord->city ?? '-' }}</td></tr>
                    <tr><th>fetched_at</th><td>{{ optional($sourceRecord->fetched_at)->format('Y-m-d H:i:s') ?? '-' }}</td></tr>
                    <tr><th>created_at</th><td>{{ optional($sourceRecord->created_at)->format('Y-m-d H:i:s') ?? '-' }}</td></tr>
                    </tbody>
                </table>
            </div>

            <details class="help-panel" style="margin-top:22px;">
                <summary>raw_jsonを確認する</summary>
                <div class="help-body">
                    <p style="margin-top:0;">CSVや外部取得元から入った元データ。編集せず、原典確認用として扱う。</p>
                    <pre>{{ json_encode($sourceRecord->raw_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}</pre>
                </div>
            </details>
        </section>
    </main>
@endsection
