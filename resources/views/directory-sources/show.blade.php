@extends('layouts.app', ['title' => '名簿元詳細 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">Directory Source #{{ $directorySource->id }}</p>
                    <h1 class="page-title">{{ $directorySource->name }}</h1>
                    <p class="page-subtitle">名簿元HPトップから2階層まで浅く探索して見つけた会員一覧・事業者一覧候補を確認する。</p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('directory-sources.index') }}">一覧へ</a>
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

            @if ($directorySource->last_error)
                <div class="error" style="margin-top:18px;">{{ $directorySource->last_error }}</div>
            @endif
        </section>

        <section class="card">
            <h2 style="margin-top:0;">会員一覧・事業者一覧候補</h2>
            <p class="muted">この候補ページを次工程の「名簿URL抽出」に渡す。ここでは営業先companyはまだ作らない。スコアが低いものも、候補0件を避けるため低信頼候補として残す。</p>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>score</th>
                        <th>候補URL</th>
                        <th>リンク文言</th>
                        <th>根拠</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($directorySource->pages as $page)
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
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">候補ページはまだない。上の「再探索」を押して。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
@endsection
