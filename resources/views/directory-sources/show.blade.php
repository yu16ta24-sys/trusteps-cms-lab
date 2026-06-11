@extends('layouts.app', ['title' => '名簿元詳細 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">Directory Source #{{ $directorySource->id }}</p>
                    <h1 class="page-title">{{ $directorySource->name }}</h1>
                    <p class="page-subtitle">名簿元HPから会員一覧ページを探し、候補ページを選んで事業者単位に抽出してからsource_recordsへ送る。</p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('directory-sources.index') }}">一覧へ</a>
                    <a class="button light" href="{{ route('directory-sources.pages', $directorySource) }}">候補ページ一覧</a>
                    @if ($directorySource->url)
                        <form method="POST" action="{{ route('directory-sources.crawl-one', $directorySource) }}" onsubmit="return confirm('この名簿元を再探索する？');">
                            @csrf
                            <button class="button" type="submit">再探索</button>
                        </form>
                    @endif
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

            <div class="grid" style="margin-top:20px;">
                <div><strong>URL</strong><div style="overflow-wrap:anywhere;">@if($directorySource->url)<a href="{{ $directorySource->url }}" target="_blank" rel="noopener">{{ $directorySource->url }}</a>@else - @endif</div></div>
                <div><strong>都道府県</strong><div>{{ $directorySource->pref_name ?? '-' }}</div></div>
                <div><strong>ドメイン</strong><div>{{ $directorySource->normalized_domain ?? '-' }}</div></div>
                <div><strong>探索状態</strong><div>{{ $directorySource->crawl_status }}</div></div>
            </div>

            @php
                $latestCrawl = data_get($directorySource->raw_json, 'latest_crawl', []);
            @endphp
            @if (!empty($latestCrawl))
                <div class="grid" style="margin-top:18px;">
                    <div><strong>事業者HP候補</strong><div>{{ number_format((int) data_get($latestCrawl, 'external_candidate_count', 0)) }}</div></div>
                    <div><strong>内部ページ候補</strong><div>{{ number_format((int) data_get($latestCrawl, 'internal_candidate_count', 0)) }}</div></div>
                    <div><strong>追加取得</strong><div>{{ number_format((int) data_get($latestCrawl, 'probe_pages', 0)) }}</div></div>
                    <div><strong>外部リンク検出</strong><div>{{ number_format((int) data_get($latestCrawl, 'external_links_found', 0)) }}</div></div>
                </div>
            @endif

            @if ($directorySource->last_error)
                <div class="error" style="margin-top:18px;">{{ $directorySource->last_error }}</div>
            @endif
        </section>

        @php
            $externalPages = $directorySource->pages->filter(fn ($page) => $page->page_type === 'member_site_candidate')->values();
            $exportableExternalPages = $externalPages->filter(fn ($page) => $page->status === 'candidate')->values();
            $internalPages = $directorySource->pages->reject(fn ($page) => $page->page_type === 'member_site_candidate')->values();
        @endphp

        <section class="card">
            <div class="row">
                <div>
                    <h2 style="margin-top:0;">事業者HP候補（外部ドメイン）</h2>
                    <p class="muted">ここが次工程に送る本命。商工会HP内ページではなく、会員一覧・事業者紹介ページ内で見つけた別ドメインのHP候補。</p>
                </div>
                @if ($exportableExternalPages->isNotEmpty())
                    <div class="actions">
                        <button class="button small light" type="button" id="check-all-member-sites">全チェック</button>
                        <button class="button small light" type="button" id="uncheck-all-member-sites">全解除</button>
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('directory-sources.pages.store-source-records', $directorySource) }}" onsubmit="return confirm('選択した事業者HP候補をsource_recordsへ保存する？');">
                @csrf
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th style="width:44px;">保存</th>
                            <th>score</th>
                            <th>候補URL</th>
                            <th>リンク文言</th>
                            <th>発見元</th>
                            <th>状態</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($externalPages as $page)
                            <tr>
                                <td>
                                    <input class="member-site-check" type="checkbox" name="directory_source_page_ids[]" value="{{ $page->id }}" @checked($page->status === 'candidate') @disabled($page->status !== 'candidate')>
                                </td>
                                <td><strong>{{ $page->score }}</strong><div class="muted">{{ $page->confidence }}</div></td>
                                <td style="max-width:420px; overflow-wrap:anywhere;">
                                    <a href="{{ $page->url }}" target="_blank" rel="noopener">{{ $page->url }}</a>
                                    <div class="muted">{{ $page->normalized_domain ?? '-' }}</div>
                                </td>
                                <td>{{ $page->link_text ?: '-' }}</td>
                                <td style="max-width:360px; overflow-wrap:anywhere;">
                                    @if ($parentUrl = data_get($page->raw_json, 'parent_url'))
                                        <a href="{{ $parentUrl }}" target="_blank" rel="noopener">{{ $parentUrl }}</a>
                                    @elseif ($page->discovered_from)
                                        <a href="{{ $page->discovered_from }}" target="_blank" rel="noopener">{{ $page->discovered_from }}</a>
                                    @else
                                        -
                                    @endif
                                    @if ($parentTitle = data_get($page->raw_json, 'parent_title'))
                                        <div class="muted">{{ $parentTitle }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($page->status === 'exported_to_source_record')
                                        <span class="badge green">保存済</span>
                                        @if ($srId = data_get($page->raw_json, 'exported_to_source_record_id'))
                                            <div class="muted">source_record #{{ $srId }}</div>
                                        @endif
                                    @else
                                        <span class="badge blue">未保存</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">外部ドメインの事業者HP候補はまだない。再探索しても0なら、その商工会サイトは会員HPを外部リンクとして出していない可能性がある。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($exportableExternalPages->isNotEmpty())
                    <div class="actions" style="margin-top:16px;">
                        <button class="button" type="submit">選択分をsource_recordsへ保存</button>
                    </div>
                @endif
            </form>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">内部ページ候補（探索用）</h2>
            <p class="muted">これは商工会HP内の会員一覧・事業者一覧っぽいページ。営業先ではないため、このままsource_recordsへは送らない。</p>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>score</th>
                        <th>候補URL</th>
                        <th>リンク文言</th>
                        <th>根拠</th>
                        <th>抽出</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($internalPages as $page)
                        <tr>
                            <td><strong>{{ $page->score }}</strong><div class="muted">{{ $page->confidence }}</div></td>
                            <td style="max-width:420px; overflow-wrap:anywhere;"><a href="{{ $page->url }}" target="_blank" rel="noopener">{{ $page->url }}</a></td>
                            <td>{{ $page->link_text ?: '-' }}</td>
                            <td>
                                @php
                                    $matched = data_get($page->raw_json, 'matched_keywords', []);
                                    $negative = data_get($page->raw_json, 'negative_keywords', []);
                                @endphp
                                @if (!empty($matched))
                                    <div>一致：{{ implode(' / ', $matched) }}</div>
                                @endif
                                @if ($stage = data_get($page->raw_json, 'source_stage'))
                                    <div class="muted">発見段階：{{ $stage }}</div>
                                @endif
                                @if ($pageTitle = data_get($page->raw_json, 'page_title'))
                                    <div class="muted">ページtitle：{{ $pageTitle }}</div>
                                @endif
                                @if (!empty($negative))
                                    <div class="muted">弱め要素：{{ implode(' / ', $negative) }}</div>
                                @endif
                            </td>
                            <td>
                                @if (in_array($page->page_type, ['member_list', 'member_list_candidate', 'member_list_candidate_deep', 'general_candidate', 'member_list_candidate'], true))
                                    <a class="button small" href="{{ route('directory-source-pages.extract', $page) }}">事業者抽出</a>
                                    @if ($page->extraction_status ?? null)
                                        <div class="muted" style="margin-top:6px;">{{ $page->extraction_status }}</div>
                                    @endif
                                @else
                                    <a class="button small light" href="{{ route('directory-source-pages.extract', $page) }}">確認抽出</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">内部ページ候補はまだない。上の「再探索」を押して。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        document.getElementById('check-all-member-sites')?.addEventListener('click', function () {
            document.querySelectorAll('.member-site-check:not(:disabled)').forEach((checkbox) => {
                checkbox.checked = true;
            });
        });
        document.getElementById('uncheck-all-member-sites')?.addEventListener('click', function () {
            document.querySelectorAll('.member-site-check:not(:disabled)').forEach((checkbox) => {
                checkbox.checked = false;
            });
        });
    </script>
@endsection
