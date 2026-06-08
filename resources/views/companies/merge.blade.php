@extends('layouts.app', ['title' => 'company統合 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="muted" style="margin:0;">Phase0-7 / 手動company統合</p>
                    <h1 style="margin:6px 0 0;">company統合</h1>
                </div>
                <a class="button light" href="{{ route('companies.show', $company) }}">company詳細へ戻る</a>
            </div>

            @if ($errors->any())
                <div class="error" style="margin-top:20px;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="card" style="box-shadow:none; margin-top:20px;">
                <h2 style="margin-top:0;">統合元company</h2>
                <div class="table-wrap">
                    <table>
                        <tbody>
                        <tr><th>ID</th><td>{{ $company->id }}</td></tr>
                        <tr><th>display_name</th><td>{{ $company->display_name }}</td></tr>
                        <tr><th>status</th><td>{{ $company->status }}</td></tr>
                        <tr><th>domain</th><td>{{ $company->primaryDomain?->normalized_domain ?? '-' }}</td></tr>
                        <tr><th>source_links</th><td>{{ $company->sourceLinks()->count() }}件</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="muted">このcompanyを、下で選ぶ既存companyへ統合する。source linksは書き換えず、merged_into_idで統合先を指す。</p>
            </div>

            <form method="GET" action="{{ route('companies.merge-form', $company) }}" class="card" style="box-shadow:none; padding:18px; margin:20px 0;">
                <div class="grid">
                    <div class="field" style="margin-bottom:0;">
                        <label for="q">統合先company検索</label>
                        <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="会社名・法人番号・地域など">
                    </div>
                    <div class="field" style="margin-bottom:0; align-self:end;">
                        <button class="button" type="submit">検索</button>
                        <a class="button light" href="{{ route('companies.merge-form', $company) }}">リセット</a>
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
                        <th>地域</th>
                        <th>domain</th>
                        <th>統合</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($targetCompanies as $target)
                        <tr>
                            <td>{{ $target->id }}</td>
                            <td><strong>{{ $target->display_name }}</strong></td>
                            <td>{{ $target->status }}</td>
                            <td>{{ $target->municipality?->prefecture?->name ?? $target->pref ?? '-' }} / {{ $target->municipality?->name ?? $target->city ?? '-' }}</td>
                            <td>{{ $target->primaryDomain?->normalized_domain ?? '-' }}</td>
                            <td>
                                <form method="POST" action="{{ route('companies.merge', $company) }}" onsubmit="return confirm('company #{{ $company->id }} を company #{{ $target->id }} へ統合する？');">
                                    @csrf
                                    <input type="hidden" name="target_company_id" value="{{ $target->id }}">
                                    <div class="field">
                                        <label for="merge_reason_{{ $target->id }}">統合理由 *</label>
                                        <textarea id="merge_reason_{{ $target->id }}" name="merge_reason" required placeholder="例：同一会社の重複レコード。source_record由来の重複を確認。"></textarea>
                                    </div>
                                    <button class="button danger" type="submit">このcompanyへ統合</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">統合先候補がない。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                {{ $targetCompanies->links() }}
            </div>
        </section>
    </main>
@endsection
