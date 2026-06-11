@extends('layouts.app', ['title' => '名簿元管理 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">Directory Sources</p>
                    <h1 class="page-title">名簿元管理</h1>
                    <p class="page-subtitle">商工会・団体・組合サイトを営業先ではなく「営業先を生む入口」として管理し、会員一覧候補を浅く探索する。</p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('source-records.index', ['source_type' => 'directory_source_candidate']) }}">名簿元source_records</a>
                    <a class="button light" href="{{ route('directory-sources.shokokai-bulk-html') }}">商工会HTML取込</a>
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

            <div class="grid" style="margin-top:22px;">
                <div class="card" style="box-shadow:none; padding:16px; background:#f8fafc;"><strong>{{ number_format($stats['total'] ?? 0) }}</strong><div class="muted">directory_sources</div></div>
                <div class="card" style="box-shadow:none; padding:16px; background:#f8fafc;"><strong>{{ number_format($stats['unregistered_source_records'] ?? 0) }}</strong><div class="muted">未登録source_records</div></div>
                <div class="card" style="box-shadow:none; padding:16px; background:#f8fafc;"><strong>{{ number_format($stats['not_crawled'] ?? 0) }}</strong><div class="muted">未探索</div></div>
                <div class="card" style="box-shadow:none; padding:16px; background:#f8fafc;"><strong>{{ number_format($stats['candidate_found'] ?? 0) }}</strong><div class="muted">候補あり</div></div>
                <div class="card" style="box-shadow:none; padding:16px; background:#f8fafc;"><strong>{{ number_format($stats['pages'] ?? 0) }}</strong><div class="muted">会員一覧候補ページ</div></div>
            </div>
        </section>

        <section class="card">
            <div class="row">
                <div>
                    <h2 style="margin:0;">次の処理</h2>
                    <p class="muted" style="margin:6px 0 0;">source_recordsに入った名簿元を確定し、未探索キューから商工会HPトップを浅くクロールする。</p>
                </div>
                <div class="actions">
                    <form method="POST" action="{{ route('directory-sources.import-source-records') }}" onsubmit="return confirm('未登録の名簿元source_recordsをdirectory_sourcesへ登録する？');">
                        @csrf
                        <button class="button" type="submit">未登録名簿元を一括登録</button>
                    </form>
                    <form method="POST" action="{{ route('directory-sources.crawl-queue') }}" onsubmit="return confirm('未探索キューの先頭を探索する？外部HPへHTTPアクセスする。');">
                        @csrf
                        <input type="hidden" name="queue_limit" value="10">
                        <input type="hidden" name="limit_per_source" value="50">
                        <button class="button light" type="submit">未探索10件を探索</button>
                    </form>
                </div>
            </div>
        </section>

        <section class="card">
            <form method="GET" action="{{ route('directory-sources.index') }}" class="card" style="box-shadow:none; padding:18px; margin:0 0 18px;">
                <div class="grid">
                    <div class="field" style="margin-bottom:0;">
                        <label for="q">語句検索</label>
                        <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="商工会名・URL・ドメインなど">
                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <label for="pref_name">都道府県</label>
                        <select id="pref_name" name="pref_name">
                            <option value="">すべて</option>
                            @foreach ($prefOptions as $pref)
                                <option value="{{ $pref }}" @selected(request('pref_name') === $pref)>{{ $pref }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <label for="crawl_status">探索状態</label>
                        <select id="crawl_status" name="crawl_status">
                            <option value="">すべて</option>
                            @foreach (['not_crawled' => '未探索', 'candidate_found' => '候補あり', 'no_candidate' => '候補なし', 'fetch_error' => '取得エラー', 'no_url' => 'URLなし'] as $key => $label)
                                <option value="{{ $key }}" @selected(request('crawl_status') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="margin-bottom:0; align-self:end;">
                        <button class="button" type="submit">絞り込み</button>
                        <a class="button light" href="{{ route('directory-sources.index') }}">リセット</a>
                    </div>
                </div>
            </form>

            <form method="POST" action="{{ route('directory-sources.crawl-selected') }}">
                @csrf
                <div class="card" style="box-shadow:none; padding:14px 18px; margin:0 0 16px; background:#f8fafc;">
                    <div class="row">
                        <div>
                            <strong>一括探索</strong>
                            <p class="muted" style="margin:6px 0 0;">チェックした名簿元HPトップから、会員一覧・事業者一覧っぽい同一ドメイン内ページを最大50件まで抽出する。</p>
                        </div>
                        <button class="button" type="submit" onclick="return confirm('選択した名簿元を探索する？');">選択分を探索</button>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th><input type="checkbox" id="check-all-directory-sources" aria-label="全選択"></th>
                            <th>ID</th>
                            <th>名簿元</th>
                            <th>URL</th>
                            <th>都道府県</th>
                            <th>状態</th>
                            <th>候補</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($directorySources as $source)
                            <tr>
                                <td><input class="directory-source-check" type="checkbox" name="directory_source_ids[]" value="{{ $source->id }}" @disabled($source->status !== 'active' || ! $source->url)></td>
                                <td>{{ $source->id }}</td>
                                <td>
                                    <strong>{{ $source->name }}</strong>
                                    <div class="muted">{{ $source->organization_type ?: '-' }}</div>
                                </td>
                                <td style="max-width:320px; overflow-wrap:anywhere;">
                                    @if ($source->url)
                                        <a href="{{ $source->url }}" target="_blank" rel="noopener">{{ $source->normalized_domain ?: $source->url }}</a>
                                        <div class="muted">{{ $source->url }}</div>
                                    @else
                                        <span class="badge gray">URLなし</span>
                                    @endif
                                </td>
                                <td>{{ $source->pref_name ?? '-' }}</td>
                                <td>
                                    @php
                                        $crawlLabels = [
                                            'not_crawled' => ['未探索', 'gray'],
                                            'candidate_found' => ['候補あり', 'green'],
                                            'no_candidate' => ['候補なし', 'gray'],
                                            'fetch_error' => ['取得エラー', 'red'],
                                            'no_url' => ['URLなし', 'gray'],
                                        ];
                                        $label = $crawlLabels[$source->crawl_status] ?? [$source->crawl_status, 'gray'];
                                    @endphp
                                    <span class="badge {{ $label[1] }}">{{ $label[0] }}</span>
                                    @if ($source->last_crawled_at)
                                        <div class="muted">{{ $source->last_crawled_at->format('Y-m-d H:i') }}</div>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ number_format($source->candidate_pages_count) }}</strong>
                                    <div class="muted">pages: {{ number_format($source->pages_count) }}</div>
                                </td>
                                <td class="actions">
                                    <a class="button small light" href="{{ route('directory-sources.show', $source) }}">詳細</a>
                                    @if ($source->url)
                                        <form method="POST" action="{{ route('directory-sources.crawl-one', $source) }}" style="display:inline;" onsubmit="return confirm('この名簿元を探索する？');">
                                            @csrf
                                            <button class="button small" type="submit">探索</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="empty-state">directory_sourcesがまだない。まず「未登録名簿元を一括登録」を押して。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </form>

            @php
                $paginator = $directorySources->appends(request()->query());
                $currentPage = $paginator->currentPage();
                $lastPage = $paginator->lastPage();
                $windowStart = max(1, $currentPage - 3);
                $windowEnd = min($lastPage, $currentPage + 3);
            @endphp

            @if ($lastPage > 1)
                <nav class="pagination compact-pagination" aria-label="directory_sources pagination" style="margin-top:18px;">
                    <div class="pagination-links">
                        @if ($paginator->onFirstPage())
                            <span class="page-link disabled">‹ Previous</span>
                        @else
                            <a class="page-link" href="{{ $paginator->previousPageUrl() }}">‹ Previous</a>
                        @endif

                        @if ($windowStart > 1)
                            <a class="page-link" href="{{ $paginator->url(1) }}">1</a>
                            @if ($windowStart > 2)
                                <span class="page-ellipsis">…</span>
                            @endif
                        @endif

                        @for ($page = $windowStart; $page <= $windowEnd; $page++)
                            @if ($page === $currentPage)
                                <span class="page-link active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                            @endif
                        @endfor

                        @if ($windowEnd < $lastPage)
                            @if ($windowEnd < $lastPage - 1)
                                <span class="page-ellipsis">…</span>
                            @endif
                            <a class="page-link" href="{{ $paginator->url($lastPage) }}">{{ $lastPage }}</a>
                        @endif

                        @if ($paginator->hasMorePages())
                            <a class="page-link" href="{{ $paginator->nextPageUrl() }}">Next ›</a>
                        @else
                            <span class="page-link disabled">Next ›</span>
                        @endif
                    </div>
                    <p class="muted" style="margin-top:8px;">
                        Showing {{ number_format($paginator->firstItem() ?? 0) }} to {{ number_format($paginator->lastItem() ?? 0) }} of {{ number_format($paginator->total()) }} results
                    </p>
                </nav>
            @endif
        </section>
    </main>

    <script>
        document.getElementById('check-all-directory-sources')?.addEventListener('change', function () {
            document.querySelectorAll('.directory-source-check:not(:disabled)').forEach((checkbox) => {
                checkbox.checked = this.checked;
            });
        });
    </script>
@endsection
