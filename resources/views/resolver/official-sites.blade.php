@extends('layouts.app', ['title' => '公式HP取得 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">Phase1 / Official Site Resolver MVP</p>
                    <h1 class="page-title">公式HP取得</h1>
                    <p class="page-subtitle">
                        URL候補を実際に取得し、title/meta/SSL/WordPress推定/問い合わせ導線を確認する。companyは自動作成しない。
                    </p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('discovery.lab') }}">候補収集ラボ</a>
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
                <summary>v0.18.6でやること / やらないこと</summary>
                <div class="help-body">
                    <div>やる：手動URLをHTTP取得し、title、meta description、generator、canonical、SSL、WordPress推定、問い合わせフォーム・メール・電話の有無を確認する。</div>
                    <div>やらない：Google検索、Googleマップ、Playwright、スクリーンショット、company自動作成、営業スコア自動反映。</div>
                </div>
            </details>

            <form method="POST" action="{{ route('resolver.official-sites.preview') }}" class="card" style="box-shadow:none; padding:20px; margin-top:22px; border-color:#bfdbfe; background:#f8fbff;">
                @csrf

                <div class="row" style="align-items:flex-start;">
                    <div>
                        <p class="section-label">official site resolver</p>
                        <h2 style="margin:0 0 8px; font-size:20px;">公式HP候補URLを取得してプレビュー</h2>
                        <p class="muted" style="margin:0; font-size:13px;">1行1URL。外部サイトへHTTPアクセスする。最大{{ number_format(config('discovery.official_site_resolver_url_limit', 30)) }}件。</p>
                    </div>
                    <span class="badge blue">HTTP取得あり</span>
                </div>

                <div class="field" style="margin-top:16px;">
                    <label for="urls">公式HP候補URL</label>
                    <textarea id="urls" name="urls" rows="8" placeholder="https://example.jp&#10;https://sample-koumuten.jp">{{ old('urls') }}</textarea>
                    @error('urls')
                        <p style="margin:8px 0 0; color:#dc2626; font-weight:800; font-size:13px;">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid" style="margin-top:16px;">
                    <div class="field">
                        <label for="source_name">source_name</label>
                        <input id="source_name" type="text" name="source_name" value="{{ old('source_name') }}" placeholder="例：候補収集ラボからの公式HP確認">
                    </div>
                    <div class="field">
                        <label for="pref">都道府県メモ</label>
                        <input id="pref" type="text" name="pref" value="{{ old('pref') }}" placeholder="例：熊本県">
                    </div>
                    <div class="field">
                        <label for="city">市町村メモ</label>
                        <input id="city" type="text" name="city" value="{{ old('city') }}" placeholder="例：熊本市">
                    </div>
                    <div class="field">
                        <label for="raw_industry">業種メモ</label>
                        <input id="raw_industry" type="text" name="raw_industry" value="{{ old('raw_industry') }}" placeholder="例：工務店">
                    </div>
                </div>

                <div class="field" style="margin-top:6px;">
                    <label for="memo">メモ</label>
                    <textarea id="memo" name="memo" rows="3" placeholder="取得元や検証条件など">{{ old('memo') }}</textarea>
                </div>

                <div class="actions" style="justify-content:flex-start; margin-top:16px;">
                    <button class="button" type="submit">取得してプレビュー</button>
                    <a class="button light" href="{{ route('resolver.official-sites.index') }}">リセット</a>
                </div>
            </form>

            @if ($preview)
                @php
                    $summary = $preview['summary'] ?? [];
                    $rows = $preview['rows'] ?? [];
                @endphp

                <section class="card" style="box-shadow:none; padding:20px; margin-top:22px;">
                    <div class="row">
                        <div>
                            <p class="section-label">preview summary</p>
                            <h2 style="margin:0 0 8px; font-size:20px;">取得結果プレビュー</h2>
                            <p class="muted" style="margin:0;">
                                総数 {{ number_format($summary['total'] ?? 0) }}件 / 取得成功 {{ number_format($summary['ok'] ?? 0) }}件 / 高信頼 {{ number_format($summary['high'] ?? 0) }}件 / WordPress推定 {{ number_format($summary['wordpress'] ?? 0) }}件 / 問い合わせ導線あり {{ number_format($summary['contact'] ?? 0) }}件 / 初期選択 {{ number_format($summary['default_checked'] ?? 0) }}件
                            </p>
                        </div>
                    </div>
                </section>

                <form method="POST" action="{{ route('resolver.official-sites.store') }}" style="margin-top:18px;">
                    @csrf
                    <input type="hidden" name="token" value="{{ $preview['token'] }}">

                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th style="width:72px;">保存</th>
                                <th style="min-width:260px;">URL / title</th>
                                <th style="min-width:220px;">取得結果</th>
                                <th style="min-width:220px;">CMS / 導線</th>
                                <th style="min-width:260px;">判定</th>
                                <th style="min-width:220px;">警告 / 重複</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_rows[]" value="{{ $row['row_id'] }}" @checked(!empty($row['default_checked']))>
                                    </td>
                                    <td>
                                        <div style="font-weight:900; word-break:break-all;">
                                            {{ $row['normalized_domain'] ?? '-' }}
                                        </div>
                                        @if (!empty($row['final_url']))
                                            <div style="margin-top:8px; word-break:break-all;"><a href="{{ $row['final_url'] }}" target="_blank" rel="noopener">{{ $row['final_url'] }}</a></div>
                                        @endif
                                        <div class="muted" style="margin-top:6px; font-size:12px;">line {{ $row['line_number'] ?? '-' }} / input: {{ $row['input_url'] ?? '-' }}</div>
                                        @if (!empty($row['title']))
                                            <div style="margin-top:8px; font-weight:800;">{{ $row['title'] }}</div>
                                        @endif
                                        @if (!empty($row['meta_description']))
                                            <div class="muted" style="margin-top:6px; font-size:12px;">{{ \Illuminate\Support\Str::limit($row['meta_description'], 180) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if (!empty($row['ok']))
                                            <div><span class="badge green">取得成功</span></div>
                                        @else
                                            <div><span class="badge red">要確認</span></div>
                                        @endif
                                        <div style="margin-top:8px;">HTTP: {{ $row['http_status'] ?? '-' }}</div>
                                        <div style="margin-top:6px;">
                                            @if (!empty($row['ssl_enabled']))
                                                <span class="badge green">SSLあり</span>
                                            @else
                                                <span class="badge red">SSLなし/未確認</span>
                                            @endif
                                        </div>
                                        @if (!empty($row['content_type']))
                                            <div class="muted" style="margin-top:6px; font-size:12px;">{{ $row['content_type'] }}</div>
                                        @endif
                                        @if (!empty($row['error']))
                                            <div style="margin-top:8px; color:#991b1b; font-weight:800;">{{ $row['error'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if (!empty($row['wordpress_detected']))
                                            <div><span class="badge blue">WordPress推定</span></div>
                                            @foreach (($row['wordpress_signals'] ?? []) as $signal)
                                                <div class="muted" style="margin-top:4px; font-size:12px;">{{ $signal }}</div>
                                            @endforeach
                                        @else
                                            <div><span class="badge gray">CMS不明</span></div>
                                        @endif

                                        @if (!empty($row['builder_guess']))
                                            <div style="margin-top:8px;"><span class="badge purple">{{ $row['builder_guess'] }}</span></div>
                                        @endif

                                        <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px;">
                                            @if (!empty($row['has_contact_form']))
                                                <span class="badge green">フォーム</span>
                                            @endif
                                            @if (!empty($row['has_public_email']))
                                                <span class="badge green">メール</span>
                                            @endif
                                            @if (!empty($row['has_phone']))
                                                <span class="badge green">電話</span>
                                            @endif
                                            @if (empty($row['has_contact_form']) && empty($row['has_public_email']) && empty($row['has_phone']))
                                                <span class="badge gray">導線未検出</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $label = $row['confidence_label'] ?? '-';
                                            $labelClass = $label === '高' ? 'green' : ($label === '中' ? 'blue' : ($label === '低' ? 'gray' : 'red'));
                                        @endphp
                                        <div><span class="badge {{ $labelClass }}">信頼度：{{ $label }}</span></div>
                                        <div style="margin-top:8px; font-weight:900;">{{ $row['recommendation_label'] ?? '-' }}</div>
                                        <div class="muted" style="margin-top:6px; font-size:12px;">{{ $row['confidence_reason'] ?? '-' }}</div>
                                        @if (!empty($row['recommendation_reason']))
                                            <div class="muted" style="margin-top:6px; font-size:12px;">{{ $row['recommendation_reason'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if (!empty($row['warnings']))
                                            @foreach ($row['warnings'] as $warning)
                                                <div style="margin-bottom:6px;"><span class="badge amber">注意</span> {{ $warning }}</div>
                                            @endforeach
                                        @endif

                                        @if (!empty($row['duplicate_signals']))
                                            @foreach ($row['duplicate_signals'] as $signal)
                                                <div style="margin-bottom:6px;"><span class="badge red">重複</span> {{ $signal }}</div>
                                            @endforeach
                                        @endif

                                        @if (empty($row['warnings']) && empty($row['duplicate_signals']))
                                            <span class="muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="form-actions sticky-ish" style="margin-top:18px;">
                        <button class="button" type="submit" onclick="return confirm('選択した公式HP取得結果をsource_recordsへ保存する？companyは自動作成しない。');">選択分をsource_recordsへ保存</button>
                        <a class="button light" href="{{ route('resolver.official-sites.index') }}">プレビュー破棄</a>
                    </div>
                </form>
            @endif
        </section>
    </main>
@endsection
