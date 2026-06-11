@extends('layouts.app', ['title' => '名簿元収集ラボ | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">Phase1 / Directory Source Lab</p>
                    <h1 class="page-title">名簿元収集ラボ</h1>
                    <p class="page-subtitle">
                        商工会・商工会議所・中央会・業界団体・公的DBなど、営業先候補を生むための「入口」を集める。営業先companyは自動作成しない。
                    </p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('discovery.lab') }}">候補収集ラボ</a>
                    <a class="button light" href="{{ route('resolver.official-sites.index') }}">公式HP取得</a>
                    <a class="button light" href="{{ route('source-records.index') }}">source_recordsへ</a>
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

            <details class="help-panel" style="margin-top:20px;" open>
                <summary>v0.18.7でやること / やらないこと</summary>
                <div class="help-body">
                    <div>やる：入口URLを取得し、ページ内リンクから「名簿元候補」を抽出・分類・保存する。</div>
                    <div>対象：商工会、商工会議所、中央会・協同組合、業界団体、生活衛生組合、観光協会、公的事業所DBなど。</div>
                    <div>やらない：Google検索、Googleマップ、会員企業の自動company化、サイト全体の深掘りクロール。</div>
                </div>
            </details>

            <form method="POST" action="{{ route('directory-sources.lab.preview') }}" class="card" style="box-shadow:none; padding:20px; margin-top:22px; border-color:#bfdbfe; background:#f8fbff;">
                @csrf

                <div class="row" style="align-items:flex-start;">
                    <div>
                        <p class="section-label">directory source collector</p>
                        <h2 style="margin:0 0 8px; font-size:20px;">入口URLから名簿元候補をプレビュー</h2>
                        <p class="muted" style="margin:0; font-size:13px;">1行1URL。外部サイトへHTTPアクセスする。最大{{ number_format(config('discovery.directory_source_entry_url_limit', 10)) }}件。</p>
                    </div>
                    <span class="badge blue">HTTP取得あり</span>
                </div>

                <div class="field" style="margin-top:16px;">
                    <label for="entry_urls">入口URL</label>
                    <textarea id="entry_urls" name="entry_urls" rows="7" placeholder="https://www.shokokai.or.jp/?page_id=1754&#10;https://example-shokokai.jp">{{ old('entry_urls') }}</textarea>
                    @error('entry_urls')
                        <p style="margin:8px 0 0; color:#dc2626; font-weight:800; font-size:13px;">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid" style="margin-top:16px;">
                    <div class="field">
                        <label for="source_name">source_name</label>
                        <input id="source_name" type="text" name="source_name" value="{{ old('source_name') }}" placeholder="例：全国商工会WEBサーチ">
                    </div>
                    <div class="field">
                        <label for="pref">都道府県メモ</label>
                        <input id="pref" type="text" name="pref" value="{{ old('pref') }}" placeholder="例：静岡県">
                    </div>
                    <div class="field">
                        <label for="city">市町村メモ</label>
                        <input id="city" type="text" name="city" value="{{ old('city') }}" placeholder="例：静岡市">
                    </div>
                </div>

                <div class="field" style="margin-top:6px;">
                    <label for="memo">メモ</label>
                    <textarea id="memo" name="memo" rows="3" placeholder="取得元や探索条件など">{{ old('memo') }}</textarea>
                </div>

                <div class="actions" style="justify-content:flex-start; margin-top:16px;">
                    <button class="button" type="submit">名簿元候補を取得してプレビュー</button>
                    <a class="button light" href="{{ route('directory-sources.lab') }}">リセット</a>
                </div>
            </form>

            @if ($preview)
                @php
                    $summary = $preview['summary'] ?? [];
                    $entryResults = $preview['entry_results'] ?? [];
                    $rows = $preview['rows'] ?? [];
                    $excluded = $preview['excluded'] ?? [];
                    $categoryOrder = [
                        'shokokai' => '商工会',
                        'chamber' => '商工会議所',
                        'cooperative' => '中央会・協同組合',
                        'industry_association' => '業界団体・協会',
                        'hygiene' => '生活衛生組合',
                        'tourism' => '観光協会',
                        'public_database' => '公的事業所DB',
                        'other' => 'その他・要確認',
                    ];
                    $groupedRows = collect($rows)->groupBy('category_key');
                @endphp

                <section class="card" style="box-shadow:none; padding:20px; margin-top:22px;">
                    <div class="row">
                        <div>
                            <p class="section-label">preview summary</p>
                            <h2 style="margin:0 0 8px; font-size:20px;">名簿元候補プレビュー</h2>
                            <p class="muted" style="margin:0; line-height:1.8;">
                                入口 {{ number_format($summary['entry_total'] ?? 0) }}件 / 取得成功 {{ number_format($summary['entry_ok'] ?? 0) }}件 / 候補 {{ number_format($summary['total'] ?? 0) }}件 / 高信頼 {{ number_format($summary['high'] ?? 0) }}件 / 初期選択 {{ number_format($summary['default_checked'] ?? 0) }}件 / 除外 {{ number_format($summary['excluded'] ?? 0) }}件
                            </p>
                        </div>
                    </div>

                    @if (!empty($entryResults))
                        <details class="help-panel" style="margin-top:14px;">
                            <summary>入口URLの取得結果を見る</summary>
                            <div class="help-body">
                                @foreach ($entryResults as $entry)
                                    <div style="margin-bottom:10px;">
                                        @if (!empty($entry['ok']))
                                            <span class="badge green">取得成功</span>
                                        @else
                                            <span class="badge red">取得失敗</span>
                                        @endif
                                        <strong style="word-break:break-all;">{{ $entry['url'] ?? $entry['input_url'] ?? '-' }}</strong>
                                        <div class="muted" style="font-size:12px; margin-top:4px;">
                                            HTTP {{ $entry['http_status'] ?? '-' }} / links {{ $entry['link_count'] ?? 0 }} / candidates {{ $entry['candidate_count'] ?? 0 }} / excluded {{ $entry['excluded_count'] ?? 0 }}
                                            @if (!empty($entry['title'])) / {{ $entry['title'] }} @endif
                                        </div>
                                        @if (!empty($entry['error']))
                                            <div style="color:#991b1b; font-weight:800; font-size:12px; margin-top:4px;">{{ $entry['error'] }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </section>

                <form method="POST" action="{{ route('directory-sources.lab.store') }}" style="margin-top:18px;">
                    @csrf
                    <input type="hidden" name="token" value="{{ $preview['token'] }}">

                    @if (empty($rows))
                        <div class="empty-state" style="margin-top:20px;">
                            <div class="empty-state-box">
                                <div class="empty-icon">0</div>
                                <h3 class="empty-title">名簿元候補は見つからなかった</h3>
                                <p class="empty-copy">入口URLに名簿元らしいリンクがないか、検索フォーム主体のページかもしれない。</p>
                            </div>
                        </div>
                    @else
                        @foreach ($categoryOrder as $categoryKey => $categoryLabel)
                        @php
                            $categoryRows = $groupedRows->get($categoryKey, collect());
                        @endphp

                        @if ($categoryRows->isNotEmpty())
                            <section class="card" style="box-shadow:none; padding:20px; margin-top:18px;">
                                <div class="row" style="align-items:center;">
                                    <div>
                                        <p class="section-label">{{ $categoryKey }}</p>
                                        <h3 style="margin:0; font-size:20px;">{{ $categoryLabel }} <span class="muted" style="font-size:14px;">{{ number_format($categoryRows->count()) }}件</span></h3>
                                    </div>
                                    <div class="actions">
                                        <button class="button light small" type="button" data-check-group="{{ $categoryKey }}">この枠を全チェック</button>
                                        <button class="button light small" type="button" data-uncheck-group="{{ $categoryKey }}">この枠を全解除</button>
                                    </div>
                                </div>

                                <div class="table-wrap" style="margin-top:14px;">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th style="width:72px;">保存</th>
                                            <th style="min-width:280px;">名簿元候補</th>
                                            <th style="min-width:180px;">種別</th>
                                            <th style="min-width:220px;">判定</th>
                                            <th style="min-width:260px;">根拠 / 重複</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach ($categoryRows as $row)
                                            @php
                                                $label = $row['confidence_label'] ?? '-';
                                                $labelClass = $label === '高' ? 'green' : ($label === '中' ? 'blue' : ($label === '低' ? 'gray' : 'red'));
                                            @endphp
                                            <tr>
                                                <td>
                                                    <input type="checkbox" data-candidate-group="{{ $categoryKey }}" name="selected_rows[]" value="{{ $row['row_id'] }}" @checked(!empty($row['default_checked']))>
                                                </td>
                                                <td>
                                                    <div style="font-weight:950;">{{ $row['display_name'] ?? '-' }}</div>
                                                    <div style="margin-top:8px; word-break:break-all;"><a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ $row['url'] }}</a></div>
                                                    <div class="muted" style="margin-top:6px; font-size:12px;">domain: {{ $row['normalized_domain'] ?? '-' }}</div>
                                                    @if (!empty($row['link_text']))
                                                        <div class="muted" style="margin-top:6px; font-size:12px;">link: {{ \Illuminate\Support\Str::limit($row['link_text'], 120) }}</div>
                                                    @endif
                                                    @if (!empty($row['entry_url']))
                                                        <div class="muted" style="margin-top:6px; font-size:12px; word-break:break-all;">entry: {{ $row['entry_url'] }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div><span class="badge blue">{{ $row['category_label'] ?? '-' }}</span></div>
                                                    <div style="margin-top:8px;"><span class="badge gray">{{ $row['source_role_label'] ?? '-' }}</span></div>
                                                    <div class="muted" style="margin-top:8px; font-size:12px;">score: {{ $row['score'] ?? 0 }}</div>
                                                </td>
                                                <td>
                                                    <div><span class="badge {{ $labelClass }}">信頼度：{{ $label }}</span></div>
                                                    <div style="margin-top:8px; font-weight:900;">{{ $row['recommendation_label'] ?? '-' }}</div>
                                                    <div class="muted" style="margin-top:6px; font-size:12px;">{{ $row['confidence_reason'] ?? '-' }}</div>
                                                    @if (!empty($row['recommendation_reason']))
                                                        <div class="muted" style="margin-top:6px; font-size:12px;">{{ $row['recommendation_reason'] }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @foreach (($row['reasons'] ?? []) as $reason)
                                                        <div style="margin-bottom:6px;"><span class="badge green">根拠</span> {{ $reason }}</div>
                                                    @endforeach

                                                    @foreach (($row['duplicate_signals'] ?? []) as $signal)
                                                        <div style="margin-bottom:6px;"><span class="badge red">重複</span> {{ $signal }}</div>
                                                    @endforeach

                                                    @if (empty($row['reasons']) && empty($row['duplicate_signals']))
                                                        <span class="muted">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        @endif
                        @endforeach

                        <div class="form-actions sticky-ish" style="margin-top:18px;">
                            <button class="button" type="submit" onclick="return confirm('選択した名簿元候補をsource_recordsへ保存する？営業先companyは自動作成しない。');">選択分をsource_recordsへ保存</button>
                            <a class="button light" href="{{ route('directory-sources.lab') }}">プレビュー破棄</a>
                        </div>
                    @endif
                </form>

                @if (!empty($excluded))
                    <details class="help-panel" style="margin-top:22px;">
                        <summary>除外されたリンクを見る（最大{{ number_format(config('discovery.directory_source_excluded_limit', 200)) }}件）</summary>
                        <div class="help-body">
                            @foreach ($excluded as $item)
                                <div style="padding:8px 0; border-bottom:1px solid #e4e7ec;">
                                    <div style="font-weight:800; word-break:break-all;">{{ $item['url'] ?? '-' }}</div>
                                    <div class="muted" style="font-size:12px; margin-top:4px;">{{ $item['excluded_reason'] ?? '-' }}</div>
                                    @if (!empty($item['link_text']))
                                        <div class="muted" style="font-size:12px; margin-top:4px;">link: {{ \Illuminate\Support\Str::limit($item['link_text'], 140) }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endif
        </section>
    </main>

    <script>
        document.addEventListener('click', function (event) {
            var checkButton = event.target.closest('[data-check-group]');
            var uncheckButton = event.target.closest('[data-uncheck-group]');
            var group = null;
            var checked = null;

            if (checkButton) {
                group = checkButton.getAttribute('data-check-group');
                checked = true;
            }

            if (uncheckButton) {
                group = uncheckButton.getAttribute('data-uncheck-group');
                checked = false;
            }

            if (!group) {
                return;
            }

            document.querySelectorAll('[data-candidate-group="' + group + '"]').forEach(function (checkbox) {
                checkbox.checked = checked;
            });
        });
    </script>
@endsection
