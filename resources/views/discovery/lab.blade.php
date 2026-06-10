@extends('layouts.app', ['title' => '候補収集ラボ | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">Phase1 / Seed Collector</p>
                    <h1 class="page-title">候補収集ラボ</h1>
                    <p class="page-subtitle">
                        v0.18.0は手動URLリスト投入だけ。HTTP取得・Googleマップスクレイピング・company自動作成は行わず、URL文字列を分類してsource_recordsへ安全に流す入口。
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
                <summary>v0.18.2でやること / やらないこと</summary>
                <div class="help-body">
                    <div>やる：URL貼り付け、名簿URLからのaタグ抽出、ドメイン正規化、URL分類、重複警告、high-fanout警告、保存前プレビュー、CSV出力、source_records保存。</div>
                    <div>やらない：Googleマップ自動探索、Places API、Web検索API、HP解析、company自動作成。名簿URL抽出では対象ページのみ低頻度でHTTP取得する。</div>
                </div>
            </details>

            <form method="POST" action="{{ route('discovery.lab.directory-preview') }}" class="card" style="box-shadow:none; padding:20px; margin-top:22px; border-color:#bfdbfe; background:#f8fbff;">
                @csrf

                <div class="row" style="align-items:flex-start;">
                    <div>
                        <p class="section-label">directory link extract</p>
                        <h2 style="margin:0 0 8px; font-size:20px;">名簿URLからリンク抽出</h2>
                        <p class="muted" style="margin:0; font-size:13px;">商工会・自治体・業界団体などの名簿ページURLを1件だけ取得し、aタグのリンクを候補URLとして抽出する。Googleマップは使わない。</p>
                    </div>
                    <span class="badge blue">HTTP取得あり</span>
                </div>

                <div class="field" style="margin-top:16px;">
                    <label for="directory_url">名簿ページURL</label>
                    <input id="directory_url" type="text" name="directory_url" value="{{ old('directory_url') }}" placeholder="https://example.jp/member-list">
                    <p class="muted" style="margin:8px 0 0; font-size:13px;">1回の実行で1ページのみ。robots.txtを確認し、抽出リンクは最大{{ number_format(config('discovery.directory_link_limit', 200)) }}件に制限する。</p>
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

            <form method="POST" action="{{ route('discovery.lab.preview') }}" class="card" style="box-shadow:none; padding:20px; margin-top:22px;">
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
                </div>

                <form method="POST" action="{{ route('discovery.lab.store') }}" style="margin-top:18px;">
                    @csrf
                    <input type="hidden" name="preview_token" value="{{ $preview['token'] }}">

                    <div class="table-wrap">
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
                            @foreach ($rows as $row)
                                <tr>
                                    <td>
                                        @if ($row['is_valid_url'])
                                            <input type="checkbox" name="selected_rows[]" value="{{ $row['row_id'] }}" @checked($row['default_checked'])>
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

                    <div class="form-actions sticky-ish">
                        <button class="button" type="submit" onclick="return confirm('選択した候補をsource_recordsに保存する？companyは自動作成しない。');">選択分をsource_recordsへ保存</button>
                        <a class="button light" href="{{ route('discovery.lab') }}">プレビュー破棄</a>
                    </div>
                </form>
            @endif
        </section>
    </main>
@endsection
