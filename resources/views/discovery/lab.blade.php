@extends('layouts.app', ['title' => '候補収集ラボ | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">Phase1 / Seed Collector</p>
                    <h1 class="page-title">候補収集ラボ</h1>
                    <p class="page-subtitle">
                        v0.18.3.2は候補プレビューをカテゴリ別に整理。公式候補・ビルダー・SNS・EC等を枠で分け、枠単位で一括チェック/解除できる。
                    </p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('source-records.index') }}">source_recordsへ</a>
                    <a class="button light" href="{{ route('source-records.import') }}">既存CSV取り込み</a>
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
                <summary>v0.18.3.2でやること / やらないこと</summary>
                <div class="help-body">
                    <div>やる：名簿URLからのaタグ抽出、事業者詳細ページ1階層掘り、名簿元と同じドメインの非表示、同一候補ドメインの集約、既存DB登録済みドメインの非表示、カテゴリ別プレビュー、枠単位の一括チェック/解除、CSV出力、source_records保存。手動URL貼り付けは補助機能として初期閉じ。</div>
                    <div>やらない：Googleマップ自動探索、Places API、Web検索API、HP解析、company自動作成。名簿URL抽出では対象ページと詳細候補ページのみ低頻度でHTTP取得する。</div>
                </div>
            </details>

            <form method="POST" action="{{ route('discovery.lab.directory-preview') }}" class="card" style="box-shadow:none; padding:20px; margin-top:22px; border-color:#bfdbfe; background:#f8fbff;">
                @csrf

                <div class="row" style="align-items:flex-start;">
                    <div>
                        <p class="section-label">directory link extract</p>
                        <h2 style="margin:0 0 8px; font-size:20px;">名簿URLからリンク抽出</h2>
                        <p class="muted" style="margin:0; font-size:13px;">商工会・自治体・業界団体などの名簿ページURLを1件だけ取得し、外部リンクを抽出する。事業者詳細ページ候補は候補表には出さず、詳細掘り下げ用の中間ページとして裏側で使う。Googleマップは使わない。</p>
                    </div>
                    <span class="badge blue">HTTP取得あり</span>
                </div>

                <div class="field" style="margin-top:16px;">
                    <label for="directory_url">名簿ページURL</label>
                    <input id="directory_url" type="text" name="directory_url" value="{{ old('directory_url') }}" placeholder="https://example.jp/member-list">
                    <p class="muted" style="margin:8px 0 0; font-size:13px;">1回の実行で名簿1ページのみ。詳細ページ掘り下げONの場合も最大{{ number_format(config('discovery.directory_detail_page_limit', 50)) }}件まで。robots.txtを確認し、抽出リンクは最大{{ number_format(config('discovery.directory_link_limit', 200)) }}件に制限する。</p>
                </div>

                <div class="grid" style="margin-top:14px;">
                    <div class="field" style="grid-column:1 / -1;">
                        <label style="display:flex; gap:10px; align-items:center; font-weight:800;">
                            <input type="checkbox" name="follow_detail_pages" value="1" @checked(old('follow_detail_pages'))>
                            事業者詳細ページを1階層だけ掘る
                        </label>
                        <p class="muted" style="margin:6px 0 0; font-size:13px;">一覧ページで「〇〇工務店」をクリックして初めて公式HPが載る商工会・団体名簿向け。内部リンクのうち事業者詳細っぽいものだけ最大件数まで取得する。</p>
                    </div>
                    <div class="field">
                        <label for="detail_page_limit">詳細ページ取得上限</label>
                        <input id="detail_page_limit" type="number" name="detail_page_limit" min="1" max="50" value="{{ old('detail_page_limit', config('discovery.directory_detail_page_limit', 50)) }}">
                    </div>
                </div>

                <div class="grid">
                    <div class="field">
                        <label for="directory_source_type">source_type</label>
                        <input id="directory_source_type" type="text" name="default_source_type" value="{{ old('default_source_type', 'discovery_lab_directory') }}">
                    </div>
                    <div class="field">
                        <label for="directory_source_name">取得元メモ</label>
                        <input id="directory_source_name" type="text" name="source_name" value="{{ old('source_name') }}" placeholder="例：長野県商工会 会員名簿">
                    </div>
                    <div class="field">
                        <label for="directory_pref">都道府県</label>
                        <input id="directory_pref" type="text" name="pref" value="{{ old('pref') }}" placeholder="例：長野県">
                    </div>
                    <div class="field">
                        <label for="directory_city">市区町村</label>
                        <input id="directory_city" type="text" name="city" value="{{ old('city') }}" placeholder="例：松本市">
                    </div>
                    <div class="field">
                        <label for="directory_raw_industry">業種ヒント</label>
                        <input id="directory_raw_industry" type="text" name="raw_industry" value="{{ old('raw_industry') }}" placeholder="例：construction">
                    </div>
                    <div class="field">
                        <label for="directory_memo">メモ</label>
                        <input id="directory_memo" type="text" name="memo" value="{{ old('memo') }}" placeholder="任意。raw_jsonに残す。">
                    </div>
                </div>

                <div class="form-actions">
                    <button class="button" type="submit">名簿URLを取得してプレビュー</button>
                </div>
            </form>

            <details class="card" style="box-shadow:none; padding:20px; margin-top:22px;">
                <summary style="cursor:pointer; font-weight:900; font-size:16px;">手動URLリスト取り込み（クリックで開く）</summary>
                <p class="muted" style="margin:10px 0 0; font-size:13px;">1行1URLで手動投入する補助機能。通常は名簿URL抽出を優先するため、初期状態では閉じている。</p>

            <form method="POST" action="{{ route('discovery.lab.preview') }}" style="margin-top:16px;">
                @csrf

                <div class="grid">
                    <div class="field">
                        <label for="default_source_type">source_type</label>
                        <input id="default_source_type" type="text" name="default_source_type" value="{{ old('default_source_type', $defaultSourceType ?? 'discovery_lab_manual') }}">
                        <p class="muted" style="margin:6px 0 0; font-size:12px;">source_recordsのsource_typeに入る。通常は discovery_lab_manual のままでOK。</p>
                    </div>
                    <div class="field">
                        <label for="source_name">取得元メモ</label>
                        <input id="source_name" type="text" name="source_name" value="{{ old('source_name') }}" placeholder="例：長野県 工務店 手動調査URL">
                    </div>
                    <div class="field">
                        <label for="pref">都道府県</label>
                        <input id="pref" type="text" name="pref" value="{{ old('pref') }}" placeholder="例：長野県">
                    </div>
                    <div class="field">
                        <label for="city">市区町村</label>
                        <input id="city" type="text" name="city" value="{{ old('city') }}" placeholder="例：松本市">
                    </div>
                    <div class="field">
                        <label for="raw_industry">業種ヒント</label>
                        <input id="raw_industry" type="text" name="raw_industry" value="{{ old('raw_industry') }}" placeholder="例：construction">
                    </div>
                    <div class="field">
                        <label for="memo">メモ</label>
                        <input id="memo" type="text" name="memo" value="{{ old('memo') }}" placeholder="任意。CSV memo/raw_jsonに残す。">
                    </div>
                </div>

                <div class="field" style="margin-top:8px;">
                    <label for="urls">URLリスト</label>
                    <textarea id="urls" name="urls" style="min-height:220px;" placeholder="https://example.com&#10;example-koumuten.jp&#10;https://www.instagram.com/example&#10;https://example.wixsite.com/site">{{ old('urls') }}</textarea>
                    <p class="muted" style="margin:8px 0 0; font-size:13px;">1行1URL。最大{{ number_format(config('discovery.manual_url_limit', 500)) }}件。URL文字列だけを見るため、外部サイトにはアクセスしない。</p>
                </div>

                <div class="form-actions">
                    <button class="button" type="submit">プレビュー生成</button>
                    <a class="button light" href="{{ route('discovery.lab') }}">リセット</a>
                </div>
            </form>

            </details>

            @if ($preview)
                @php
                    $summary = $preview['summary'] ?? [];
                    $rows = $preview['rows'] ?? [];
                    $meta = $preview['meta'] ?? [];
                @endphp

                <div class="card" style="box-shadow:none; padding:18px; margin-top:22px; background:#f8fafc;">
                    <div class="row">
                        <div>
                            <p class="section-label">preview summary</p>
                            <strong>プレビュー結果</strong>
                            <p class="muted" style="margin:6px 0 0;">
                                総数 {{ number_format($summary['total'] ?? 0) }}件 / 有効URL {{ number_format($summary['valid'] ?? 0) }}件 / 初期選択 {{ number_format($summary['default_checked'] ?? 0) }}件 / 重複警告 {{ number_format($summary['duplicate'] ?? 0) }}件 / high-fanout {{ number_format($summary['high_fanout'] ?? 0) }}件
                            </p>
                        </div>
                        <div class="actions">
                            <form method="POST" action="{{ route('discovery.lab.export-csv') }}">
                                @csrf
                                <input type="hidden" name="preview_token" value="{{ $preview['token'] }}">
                                <button class="button light" type="submit">CSV出力（全有効URL）</button>
                            </form>
                        </div>
                    </div>

                    @if (!empty($summary['by_classification']))
                        <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:8px;">
                            @foreach ($summary['by_classification'] as $classification => $count)
                                <span class="badge gray">{{ $classification }}：{{ number_format($count) }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($meta['fetch_warnings']))
                        <div style="margin-top:12px;">
                            @foreach ($meta['fetch_warnings'] as $warning)
                                <div><span class="badge amber">取得注意</span> {{ $warning }}</div>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($meta['detail_stats']))
                        @php($detailStats = $meta['detail_stats'])
                        <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:8px;">
                            <span class="badge {{ !empty($detailStats['enabled']) ? 'blue' : 'gray' }}">詳細掘り下げ：{{ !empty($detailStats['enabled']) ? 'ON' : 'OFF' }}</span>
                            <span class="badge gray">詳細候補：{{ number_format($detailStats['candidates'] ?? 0) }}</span>
                            <span class="badge gray">取得：{{ number_format($detailStats['fetched'] ?? 0) }}</span>
                            <span class="badge gray">詳細内外部リンク：{{ number_format($detailStats['external_links_found'] ?? 0) }}</span>
                            <span class="badge gray">上限：{{ number_format($detailStats['limit'] ?? 0) }}</span>
                        </div>
                    @endif


                    @if (!empty($meta['filter_stats']))
                        @php($filterStats = $meta['filter_stats'])
                        @php($detailStats = $meta['detail_stats'] ?? [])
                        <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:8px;">
                            <span class="badge blue">候補ノイズ削減ON</span>
                            <span class="badge gray">名簿元ドメイン非表示：{{ number_format($filterStats['source_domain_hidden'] ?? 0) }}</span>
                            <span class="badge gray">同一候補ドメイン非表示：{{ number_format(($filterStats['duplicate_domain_hidden'] ?? 0) + ($filterStats['preview_duplicate_domain_hidden'] ?? 0)) }}</span>
                            <span class="badge gray">同一URL非表示：{{ number_format($filterStats['duplicate_url_hidden'] ?? 0) }}</span>
                            <span class="badge gray">既存DBドメイン非表示：{{ number_format($filterStats['existing_domain_hidden'] ?? 0) }}</span>
                            <span class="badge gray">詳細候補は裏側のみ：{{ number_format($detailStats['hidden_from_final_candidates'] ?? 0) }}</span>
                        </div>
                    @endif
                </div>

                @php
                    $candidateGroups = [
                        'official' => [
                            'label' => '公式候補',
                            'description' => '独自ドメインなど、公式HPの可能性が高い候補。基本的にはここを優先して確認・保存する。',
                            'classifications' => ['official_site_candidate'],
                            'badge' => 'green',
                            'open' => true,
                        ],
                        'builder' => [
                            'label' => 'ビルダー系',
                            'description' => 'Wix / Jimdo / ペライチ等。公式HPの代替として使われている可能性があるため観測対象。',
                            'classifications' => ['builder_site_candidate'],
                            'badge' => 'purple',
                            'open' => true,
                        ],
                        'sns' => [
                            'label' => 'SNS',
                            'description' => 'Instagram / Facebook / X / YouTube等。公式HPではないため低優先。必要な場合だけ保存する。',
                            'classifications' => ['sns_candidate'],
                            'badge' => 'blue',
                            'open' => false,
                        ],
                        'ec' => [
                            'label' => 'EC・モール',
                            'description' => 'BASE / STORES / 楽天 / Yahoo等。店舗実体の補助情報として扱う。',
                            'classifications' => ['ec_candidate'],
                            'badge' => 'teal',
                            'open' => false,
                        ],
                        'portal' => [
                            'label' => 'ポータル',
                            'description' => '食べログ・SUUMO等のポータル。営業候補URLとしては原則低優先。',
                            'classifications' => ['portal_candidate'],
                            'badge' => 'amber',
                            'open' => false,
                        ],
                        'map' => [
                            'label' => 'Map',
                            'description' => 'Googleマップ等。規約・用途の都合上、公式候補としては扱わない。',
                            'classifications' => ['map_candidate'],
                            'badge' => 'red',
                            'open' => false,
                        ],
                        'pdf' => [
                            'label' => 'PDF',
                            'description' => 'PDFリンク。公式HP本体ではないため、原則保存しない。',
                            'classifications' => ['pdf_candidate'],
                            'badge' => 'gray',
                            'open' => false,
                        ],
                        'other' => [
                            'label' => 'その他・不明',
                            'description' => '分類不能・短縮URL・共有ドメイン等。必要に応じて手動確認する。',
                            'classifications' => ['unknown', 'directory_detail_candidate'],
                            'badge' => 'gray',
                            'open' => false,
                        ],
                    ];

                    $groupedRows = [];
                    foreach ($candidateGroups as $groupKey => $groupConfig) {
                        $groupedRows[$groupKey] = [];
                    }

                    foreach ($rows as $row) {
                        $classification = $row['classification'] ?? 'unknown';
                        $targetGroup = 'other';
                        foreach ($candidateGroups as $groupKey => $groupConfig) {
                            if (in_array($classification, $groupConfig['classifications'], true)) {
                                $targetGroup = $groupKey;
                                break;
                            }
                        }
                        $groupedRows[$targetGroup][] = $row;
                    }
                @endphp

                <form method="POST" action="{{ route('discovery.lab.store') }}" style="margin-top:18px;">
                    @csrf
                    <input type="hidden" name="preview_token" value="{{ $preview['token'] }}">

                    @foreach ($candidateGroups as $groupKey => $groupConfig)
                        @php
                            $groupRows = $groupedRows[$groupKey] ?? [];
                            $groupTotal = count($groupRows);
                            $groupValid = collect($groupRows)->where('is_valid_url', true)->count();
                            $groupChecked = collect($groupRows)->where('default_checked', true)->count();
                        @endphp

                        @continue($groupTotal === 0)

                        <details class="card" style="box-shadow:none; padding:16px; margin-top:14px;" @if(!empty($groupConfig['open'])) open @endif>
                            <summary style="cursor:pointer; display:flex; justify-content:space-between; gap:12px; align-items:center;">
                                <span>
                                    <span class="badge {{ $groupConfig['badge'] ?? 'gray' }}">{{ $groupConfig['label'] }}</span>
                                    <strong style="margin-left:8px;">{{ number_format($groupTotal) }}件</strong>
                                    <span class="muted" style="margin-left:8px; font-size:12px;">有効 {{ number_format($groupValid) }}件 / 初期選択 {{ number_format($groupChecked) }}件</span>
                                </span>
                            </summary>

                            <p class="muted" style="margin:10px 0 0; font-size:13px;">{{ $groupConfig['description'] }}</p>

                            <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:8px;">
                                <button class="button light" type="button" data-candidate-group-action="check" data-candidate-group="{{ $groupKey }}">この枠を全チェック</button>
                                <button class="button light" type="button" data-candidate-group-action="uncheck" data-candidate-group="{{ $groupKey }}">この枠を全解除</button>
                            </div>

                            <div class="table-wrap" style="margin-top:12px;">
                                <table>
                                    <thead>
                                    <tr>
                                        <th>保存</th>
                                        <th>分類</th>
                                        <th>URL / domain</th>
                                        <th>confidence</th>
                                        <th>警告</th>
                                        <th>重複</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($groupRows as $row)
                                        <tr>
                                            <td>
                                                @if ($row['is_valid_url'])
                                                    <input class="candidate-checkbox candidate-group-{{ $groupKey }}" type="checkbox" name="selected_rows[]" value="{{ $row['row_id'] }}" @checked($row['default_checked'])>
                                                @else
                                                    <span class="muted">不可</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $row['badge_color'] ?? 'gray' }}">{{ $row['classification_label'] ?? '不明' }}</span>
                                                <div class="muted" style="margin-top:6px; font-size:12px;">{{ $row['classification'] ?? 'unknown' }}</div>
                                            </td>
                                            <td style="overflow-wrap:anywhere; min-width:260px;">
                                                @if (!empty($row['link_text']))
                                                    <div style="font-weight:800; margin-bottom:4px;">{{ $row['link_text'] }}</div>
                                                @endif
                                                <strong>{{ $row['normalized_domain'] ?? '-' }}</strong>
                                                <div class="muted" style="margin-top:4px;">{{ $row['normalized_url'] ?? $row['input_line'] }}</div>
                                                @if (!empty($row['link_context']) && $row['link_context'] !== ($row['link_text'] ?? ''))
                                                    <div class="muted" style="margin-top:4px; font-size:12px; max-width:420px;">{{ $row['link_context'] }}</div>
                                                @endif
                                                @if (!empty($row['detail_page_url']))
                                                    <div style="margin-top:6px; font-size:12px;">
                                                        <span class="badge blue">詳細ページ由来</span>
                                                        <span class="muted" style="overflow-wrap:anywhere;">{{ $row['detail_page_url'] }}</span>
                                                    </div>
                                                @elseif (($row['classification'] ?? '') === 'directory_detail_candidate')
                                                    <div style="margin-top:6px; font-size:12px;">
                                                        <span class="badge gray">詳細ページ候補</span>
                                                        <span class="muted">詳細掘り下げONで公式HP候補を探す対象</span>
                                                    </div>
                                                @endif
                                                <div class="muted" style="margin-top:4px; font-size:12px;">line {{ $row['line_number'] }}</div>
                                            </td>
                                            <td>{{ number_format((float) ($row['confidence'] ?? 0), 2) }}</td>
                                            <td style="min-width:260px;">
                                                @if (!empty($row['warnings']))
                                                    @foreach ($row['warnings'] as $warning)
                                                        <div><span class="badge amber">注意</span> {{ $warning }}</div>
                                                    @endforeach
                                                @else
                                                    <span class="muted">-</span>
                                                @endif
                                            </td>
                                            <td style="min-width:220px;">
                                                @if (!empty($row['duplicate_signals']))
                                                    @foreach ($row['duplicate_signals'] as $signal)
                                                        <div><span class="badge red">重複</span> {{ $signal }}</div>
                                                    @endforeach
                                                @else
                                                    <span class="muted">-</span>
                                                @endif
                                                @if (!empty($row['high_fanout_warning']))
                                                    <div style="margin-top:6px;"><span class="badge purple">fanout</span> 既存+今回で{{ number_format(($row['fanout_count'] ?? 0) + 1) }}件以上の可能性</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    @endforeach

                    <div class="form-actions sticky-ish">
                        <button class="button" type="submit" onclick="return confirm('選択した候補をsource_recordsに保存する？companyは自動作成しない。');">選択分をsource_recordsへ保存</button>
                        <a class="button light" href="{{ route('discovery.lab') }}">プレビュー破棄</a>
                    </div>
                </form>

                <script>
                    (function () {
                        document.querySelectorAll('[data-candidate-group-action]').forEach(function (button) {
                            button.addEventListener('click', function () {
                                var group = button.getAttribute('data-candidate-group');
                                var action = button.getAttribute('data-candidate-group-action');
                                document.querySelectorAll('.candidate-group-' + group).forEach(function (checkbox) {
                                    checkbox.checked = action === 'check';
                                });
                            });
                        });
                    })();
                </script>
            @endif
        </section>
    </main>
@endsection
