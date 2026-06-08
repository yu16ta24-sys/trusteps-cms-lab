@extends('layouts.app', ['title' => 'Companies | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="muted" style="margin:0;">Phase0-5/7 / 正規化企業マスタ</p>
                    <h1 style="margin:6px 0 0;">companies</h1>
                </div>
                <a class="button light" href="{{ route('source-records.index') }}">source_recordsへ</a>
            </div>

            @if (session('status'))
                <div class="status" style="margin-top:20px;">{{ session('status') }}</div>
            @endif

            <p class="muted">総件数：{{ number_format($totalCount) }} 件</p>

            <form method="GET" action="{{ route('companies.index') }}" class="card" style="box-shadow:none; padding:18px; margin:20px 0;">
                <div class="grid">
                    <div class="field" style="margin-bottom:0;">
                        <label for="q">検索</label>
                        <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="会社名・法人番号・地域など">
                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <label for="industry_id">業種</label>
                        <select id="industry_id" name="industry_id">
                            <option value="">すべて</option>
                            @foreach ($industries as $industry)
                                <option value="{{ $industry->id }}" @selected((string) request('industry_id') === (string) $industry->id)>
                                    {{ $industry->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <label for="status">status</label>
                        <select id="status" name="status">
                            <option value="">すべて</option>
                            <option value="candidate" @selected(request('status') === 'candidate')>candidate</option>
                            <option value="confirmed" @selected(request('status') === 'confirmed')>confirmed</option>
                            <option value="merged" @selected(request('status') === 'merged')>merged</option>
                        </select>
                    </div>
                    <div class="field" style="margin-bottom:0; align-self:end;">
                        <button class="button" type="submit">絞り込み</button>
                        <a class="button light" href="{{ route('companies.index') }}">リセット</a>
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
                        <th>統合先</th>
                        <th></th>
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
                            <td>
                                <span class="badge {{ $company->status === 'merged' ? 'gray' : 'green' }}">{{ $company->status }}</span>
                            </td>
                            <td>{{ $company->industry?->name ?? '-' }}</td>
                            <td>
                                {{ $company->municipality?->prefecture?->name ?? $company->pref ?? '-' }}
                                /
                                {{ $company->municipality?->name ?? $company->city ?? '-' }}
                            </td>
                            <td>{{ $company->primaryDomain?->normalized_domain ?? '-' }}</td>
                            <td>
                                @if ($company->mergedInto)
                                    #{{ $company->mergedInto->id }} {{ $company->mergedInto->display_name }}
                                @else
                                    -
                                @endif
                            </td>
                            <td><a class="button small light" href="{{ route('companies.show', $company) }}">詳細</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">まだcompaniesがない。</td>
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
