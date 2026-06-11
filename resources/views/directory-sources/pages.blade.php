@extends('layouts.app', ['title' => '候補ページ一覧 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">Directory Source #{{ $directorySource->id }}</p>
                    <h1 class="page-title">候補ページ一覧</h1>
                    <p class="page-subtitle">内部ページをそのまま営業候補にせず、会員一覧っぽいページだけを選んで事業者抽出に進む。</p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('directory-sources.show', $directorySource) }}">名簿元詳細へ</a>
                    <form method="POST" action="{{ route('directory-sources.crawl-one', $directorySource) }}" onsubmit="return confirm('この名簿元を再探索する？');">
                        @csrf
                        <button class="button" type="submit">再探索</button>
                    </form>
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
        </section>

        @php
            $groups = [
                'member_list' => ['label' => '会員一覧候補', 'pages' => $pages->whereIn('page_type', ['member_list', 'member_list_candidate', 'member_list_candidate_deep'])],
                'general' => ['label' => '要確認候補', 'pages' => $pages->whereIn('page_type', ['general_candidate', 'general'])],
                'external' => ['label' => '外部ドメイン候補（旧方式）', 'pages' => $pages->where('page_type', 'member_site_candidate')],
                'noise' => ['label' => '低信頼・除外寄り', 'pages' => $pages->whereIn('page_type', ['noise', 'unknown'])],
            ];
        @endphp

        @foreach ($groups as $key => $group)
            <section class="card">
                <h2 style="margin-top:0;">{{ $group['label'] }} <span class="muted">{{ $group['pages']->count() }}件</span></h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>score</th>
                            <th>page type</th>
                            <th>URL</th>
                            <th>リンク文言/タイトル</th>
                            <th>状態</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($group['pages'] as $page)
                            <tr>
                                <td><strong>{{ $page->score }}</strong><div class="muted">{{ $page->confidence }}</div></td>
                                <td>{{ $page->page_type }}<div class="muted">{{ $page->extraction_status ?? 'pending' }}</div></td>
                                <td style="max-width:420px; overflow-wrap:anywhere;"><a href="{{ $page->url }}" target="_blank" rel="noopener">{{ $page->url }}</a></td>
                                <td>
                                    {{ $page->link_text ?: '-' }}
                                    @if ($page->title)
                                        <div class="muted">{{ $page->title }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if (($page->extracted_business_candidates_count ?? 0) > 0)
                                        <span class="badge green">抽出済 {{ $page->extracted_business_candidates_count }}件</span>
                                    @else
                                        <span class="badge blue">未抽出</span>
                                    @endif
                                </td>
                                <td><a class="button small" href="{{ route('directory-source-pages.extract', $page) }}">事業者抽出</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">該当ページなし。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </main>
@endsection
