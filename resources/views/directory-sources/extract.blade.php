@extends('layouts.app', ['title' => '事業者抽出プレビュー | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">Directory Source Page #{{ $page->id }}</p>
                    <h1 class="page-title">事業者抽出プレビュー</h1>
                    <p class="page-subtitle">ページ単位ではなく、事業者ブロック単位で抽出し、選択分だけsource_recordsへ保存する。</p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('directory-sources.show', $page->directorySource) }}">名簿元詳細へ</a>
                    <a class="button light" href="{{ route('directory-sources.pages', $page->directorySource) }}">候補ページ一覧へ</a>
                    <a class="button" href="{{ route('directory-source-pages.extract', ['page' => $page->id, 'force' => 1]) }}" onclick="return confirm('既存の未保存候補を作り直して再抽出する？');">再抽出</a>
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
                <div><strong>対象ページ</strong><div style="overflow-wrap:anywhere;"><a href="{{ $page->url }}" target="_blank" rel="noopener">{{ $page->url }}</a></div></div>
                <div><strong>名簿元</strong><div>{{ $page->directorySource->name }}</div></div>
                <div><strong>抽出件数</strong><div>{{ number_format($stats['total']) }}件</div></div>
                <div><strong>URLあり</strong><div>{{ number_format($stats['with_url']) }}件</div></div>
            </div>
        </section>

        <section class="card">
            <div class="row">
                <div>
                    <h2 style="margin-top:0;">事業者候補</h2>
                    <p class="muted">公式HP候補があるものを優先して保存。URLなし/SNSのみは確認用として残す。</p>
                </div>
                <div class="actions">
                    <button class="button small light" type="button" id="check-high">高信頼URLをチェック</button>
                    <button class="button small light" type="button" id="uncheck-all">全解除</button>
                </div>
            </div>

            <form method="POST" action="{{ route('directory-source-pages.save-candidates', $page) }}" onsubmit="return confirm('選択した事業者候補をsource_recordsへ保存する？');">
                @csrf
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th style="width:44px;">保存</th>
                            <th>事業者名</th>
                            <th>HP候補URL</th>
                            <th>信頼度</th>
                            <th>住所/TEL</th>
                            <th>抽出方法</th>
                            <th>状態</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($page->extractedBusinessCandidates as $candidate)
                            <tr @if($candidate->save_status === 'saved') style="opacity:.6;" @endif>
                                <td>
                                    <input class="candidate-check" data-confidence="{{ $candidate->url_confidence }}" type="checkbox" name="candidate_ids[]" value="{{ $candidate->id }}" @checked($candidate->url_candidate && $candidate->url_confidence >= 65 && $candidate->save_status === 'pending') @disabled($candidate->save_status !== 'pending' || ! $candidate->url_candidate)>
                                </td>
                                <td>
                                    <strong>{{ $candidate->business_name ?: '名称未取得' }}</strong>
                                    @if ($candidate->business_type)
                                        <div class="muted">{{ $candidate->business_type }}</div>
                                    @endif
                                    @if (!empty($candidate->sns_urls))
                                        <div class="muted">SNS {{ count($candidate->sns_urls) }}件</div>
                                    @endif
                                </td>
                                <td style="max-width:420px; overflow-wrap:anywhere;">
                                    @if ($candidate->url_candidate)
                                        <a href="{{ $candidate->url_candidate }}" target="_blank" rel="noopener">{{ $candidate->url_candidate }}</a>
                                        <div class="muted">{{ $candidate->normalized_domain }}</div>
                                    @else
                                        <span class="muted">URLなし</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $candidate->url_confidence }}</strong>
                                    <div class="muted">{{ $candidate->url_type ?: '-' }}</div>
                                </td>
                                <td>
                                    @if ($candidate->address)<div>{{ $candidate->address }}</div>@endif
                                    @if ($candidate->tel)<div class="muted">TEL {{ $candidate->tel }}</div>@endif
                                    @if ($candidate->fax)<div class="muted">FAX {{ $candidate->fax }}</div>@endif
                                </td>
                                <td>{{ $candidate->extraction_method ?: '-' }}</td>
                                <td>
                                    @if ($candidate->save_status === 'saved')
                                        <span class="badge green">保存済</span>
                                        @if ($candidate->source_record_id)<div class="muted">source_record #{{ $candidate->source_record_id }}</div>@endif
                                    @elseif ($candidate->save_status === 'duplicate')
                                        <span class="badge orange">重複</span>
                                    @elseif (! $candidate->url_candidate)
                                        <span class="badge">URLなし</span>
                                    @else
                                        <span class="badge blue">未保存</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty-state">事業者候補が抽出できなかった。このページは会員一覧ではないか、HTML構造が未対応の可能性あり。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($page->extractedBusinessCandidates->where('save_status', 'pending')->whereNotNull('url_candidate')->count() > 0)
                    <div class="actions" style="margin-top:16px;">
                        <button class="button" type="submit">選択分をsource_recordsへ保存</button>
                    </div>
                @endif
            </form>
        </section>
    </main>

    <script>
        document.getElementById('check-high')?.addEventListener('click', function () {
            document.querySelectorAll('.candidate-check:not(:disabled)').forEach((checkbox) => {
                checkbox.checked = Number(checkbox.dataset.confidence || 0) >= 65;
            });
        });
        document.getElementById('uncheck-all')?.addEventListener('click', function () {
            document.querySelectorAll('.candidate-check:not(:disabled)').forEach((checkbox) => {
                checkbox.checked = false;
            });
        });
    </script>
@endsection
