@extends('layouts.app', ['title' => '既存companyへリンク | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">manual link / source to company</p>
                    <h1 class="page-title">既存companyへリンク</h1>
                    <p class="page-subtitle">
                        source_recordを既存companyへ手動リンクする画面。自動統合ではなく、目視確認した確定リンクだけを保存する。
                    </p>
                </div>
                <a class="button light" href="{{ route('source-records.show', $sourceRecord) }}">source_recordへ戻る</a>
            </div>

            <details class="help-panel">
                <summary>リンク判断の注意</summary>
                <div class="help-body">
                    <div>同名・同電話・同住所だけで同一会社と決めない。ドメイン、法人番号、地域、屋号の文脈を合わせて見る。</div>
                    <div>確信が弱い場合はリンクせず、新規company作成または保留にする。誤リンクは分析データを汚染する。</div>
                </div>
            </details>

            @if ($errors->any())
                <div class="error" style="margin-top:20px;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="info-strip" style="margin-top:20px;">
                <div class="row">
                    <div>
                        <p class="section-label">source</p>
                        <strong>{{ $sourceRecord->name_norm ?? 'source_record #' . $sourceRecord->id }}</strong>
                        <div class="muted" style="margin-top:6px; overflow-wrap:anywhere;">
                            {{ $sourceRecord->source_url ?? '-' }} / {{ $sourceRecord->pref ?? '-' }} / {{ $sourceRecord->city ?? '-' }}
                        </div>
                    </div>
                    <span class="badge gray">source_type：{{ $sourceRecord->source_type }}</span>
                </div>
            </div>

            <form method="GET" action="{{ route('companies.link-existing-from-source', $sourceRecord) }}" class="form-section compact" style="margin:20px 0;">
                <div class="row">
                    <div style="flex:1 1 320px;">
                        <label for="q">既存company検索</label>
                        <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="会社名・法人番号・地域など">
                        <p class="field-hint">同一性が強い候補だけを選ぶ。弱い場合はリンクせず保留。</p>
                    </div>
                    <div class="actions" style="align-self:end;">
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
                                    <div class="actions" style="gap:6px; justify-content:flex-start;">
                                        <button class="button small" type="submit" name="after_action" value="company">リンク</button>
                                        <button class="button small light" type="submit" name="after_action" value="next_source">リンクして次へ</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-state-box">
                                        <div class="empty-icon">0</div>
                                        <h3 class="empty-title">候補が見つからない</h3>
                                        <p class="empty-copy">検索語を変えるか、新規company作成へ進む。</p>
                                    </div>
                                </div>
                            </td>
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
