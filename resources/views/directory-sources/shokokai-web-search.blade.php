@extends('layouts.app')

@section('content')
    @php
        $prefectures = $prefectures ?? [];
        $preview = $preview ?? null;
        $rows = collect($preview['rows'] ?? []);
        $excluded = $preview['excluded'] ?? [];
        $summary = $preview['summary'] ?? [];
        $pageResults = $preview['page_results'] ?? [];
        $meta = $preview['meta'] ?? [];
        $categoryOrder = [
            'shokokai' => '商工会',
            'shokokai_federation' => '商工会連合会',
        ];
        $groupedRows = $rows->groupBy('category_key');
        $invalidRows = $rows->filter(fn ($row) => empty($row['storable']));
    @endphp

    <main class="content">
        <section class="card">
            <p class="page-kicker">Directory Source Collector</p>
            <h1 class="page-title">全国商工会WEBサーチ収集</h1>
            <p class="page-subtitle">
                全国商工会連合会のWEBサーチに都道府県条件をPOSTし、地域商工会HPを名簿元候補として集める。search.php直叩きで結果が出ない場合は、条件選択ページ経由も試す。
                ここでは営業先companyは作らず、source_recordsに「名簿元」として保存する。
            </p>

            @if (session('status'))
                <div class="alert success" style="margin-top:18px;">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert error" style="margin-top:18px;">
                    <strong>確認して。</strong>
                    <ul style="margin:8px 0 0 18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('directory-sources.shokokai-web-search.preview') }}" style="margin-top:24px;">
                @csrf

                <div class="grid two">
                    <div>
                        <label for="pref_code">都道府県</label>
                        <select id="pref_code" name="pref_code" required>
                            <option value="">選択して</option>
                            @foreach ($prefectures as $code => $label)
                                <option value="{{ $code }}" @selected(old('pref_code', $meta['pref_code'] ?? '') === $code)>{{ $code }} {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="shokokai">商工会名キーワード（任意）</label>
                        <input id="shokokai" type="text" name="shokokai" value="{{ old('shokokai', $meta['shokokai'] ?? '') }}" placeholder="例：清水、東、南 など">
                    </div>
                </div>

                <div class="grid two" style="margin-top:16px;">
                    <div>
                        <label for="kensu">1ページ表示件数</label>
                        <select id="kensu" name="kensu" required>
                            @foreach ([10, 50, 100] as $option)
                                <option value="{{ $option }}" @selected((int) old('kensu', $meta['kensu'] ?? 50) === $option)>{{ $option }}件</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="max_pages">最大ページ数</label>
                        <input id="max_pages" type="number" name="max_pages" min="1" max="20" value="{{ old('max_pages', $meta['max_pages'] ?? 5) }}" required>
                    </div>
                </div>

                <details class="help-panel" style="margin-top:18px;">
                    <summary>この機能の位置づけ</summary>
                    <div class="help-body">
                        <div>全国商工会WEBサーチの検索結果から、各地の商工会HPを集める専用アダプタ。</div>
                        <div>保存先はsource_records。保存後は、各商工会HPを起点に「会員一覧・事業者一覧ページ探索」へ渡す。</div>
                    </div>
                </details>

                <div class="form-actions" style="margin-top:20px;">
                    <button class="button" type="submit">商工会WEBサーチを取得してプレビュー</button>
                    <a class="button light" href="{{ route('directory-sources.lab') }}">名簿元収集ラボへ戻る</a>
                </div>
            </form>
        </section>

        @if ($preview)
            <section class="card" style="margin-top:24px;">
                <p class="page-kicker">Preview</p>
                <h2 style="margin:0; font-size:26px;">取得結果</h2>

                <div class="stats" style="margin-top:18px;">
                    <div class="stat-card">
                        <div class="stat-label">都道府県</div>
                        <div class="stat-value">{{ $meta['pref_label'] ?? '-' }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">候補</div>
                        <div class="stat-value">{{ number_format($summary['total'] ?? 0) }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">初期チェック</div>
                        <div class="stat-value">{{ number_format($summary['default_checked'] ?? 0) }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">URL要確認</div>
                        <div class="stat-value">{{ number_format($summary['invalid'] ?? 0) }}</div>
                    </div>
                </div>

                @if (!empty($summary['stop_reason']))
                    <div class="info-strip" style="margin-top:16px;">
                        停止理由：{{ $summary['stop_reason'] }}
                    </div>
                @endif

                @if (!empty($pageResults))
                    <details class="help-panel" style="margin-top:18px;">
                        <summary>取得ページログを見る</summary>
                        <div class="help-body">
                            @foreach ($pageResults as $page)
                                <div style="padding:8px 0; border-bottom:1px solid #e4e7ec;">
                                    <strong>{{ $page['page'] ?? '-' }}ページ目</strong>
                                    <span class="muted">HTTP: {{ $page['http_status'] ?? '-' }} / 新規: {{ $page['new_row_count'] ?? 0 }} / 抽出: {{ $page['row_count'] ?? 0 }}</span>
                                    @if (!empty($page['error']))
                                        <div style="color:var(--danger); margin-top:4px;">{{ $page['error'] }}</div>
                                    @endif

                                    @if (!empty($page['attempts']))
                                        <div style="margin-top:8px; padding:8px; background:#f8fafc; border:1px solid #e4e7ec; border-radius:10px;">
                                            <div class="muted" style="font-weight:900; margin-bottom:6px;">初回POST試行ログ</div>
                                            @foreach ($page['attempts'] as $attempt)
                                                <div class="muted" style="font-size:12px; margin-top:4px;">
                                                    {{ $attempt['label'] ?? '-' }} / HTTP: {{ $attempt['http_status'] ?? '-' }} / 抽出: {{ $attempt['row_count'] ?? 0 }} / 除外: {{ $attempt['excluded_count'] ?? 0 }}
                                                    @if (!empty($attempt['error']))
                                                        / {{ $attempt['error'] }}
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif

                <form method="POST" action="{{ route('directory-sources.shokokai-web-search.store') }}" style="margin-top:18px;">
                    @csrf
                    <input type="hidden" name="token" value="{{ $preview['token'] }}">

                    @if ($rows->isEmpty())
                        <div class="empty-state" style="margin-top:20px;">
                            <div class="empty-state-box">
                                <div class="empty-icon">0</div>
                                <h3 class="empty-title">商工会HP候補は見つからなかった</h3>
                                <p class="empty-copy">条件を変えるか、最大ページ数を増やして再実行して。</p>
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
                                                <th style="min-width:280px;">商工会HP</th>
                                                <th style="min-width:220px;">住所 / TEL</th>
                                                <th style="min-width:220px;">判定</th>
                                                <th style="min-width:260px;">根拠 / 重複</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach ($categoryRows as $row)
                                                @php
                                                    $label = $row['confidence_label'] ?? '-';
                                                    $labelClass = $label === '高' ? 'green' : ($label === '中' ? 'blue' : ($label === '要確認' ? 'red' : 'gray'));
                                                    $isStorable = !empty($row['storable']);
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" data-candidate-group="{{ $categoryKey }}" name="selected_rows[]" value="{{ $row['row_id'] }}" @checked(!empty($row['default_checked'])) @disabled(!$isStorable)>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight:950;">{{ $row['organization_name'] ?? '-' }}</div>
                                                        <div style="margin-top:8px; word-break:break-all;">
                                                            @if ($isStorable)
                                                                <a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ $row['url'] }}</a>
                                                            @else
                                                                <span style="color:var(--danger);">{{ $row['url'] ?? $row['raw_href'] ?? '-' }}</span>
                                                            @endif
                                                        </div>
                                                        <div class="muted" style="margin-top:6px; font-size:12px;">domain: {{ $row['normalized_domain'] ?? '-' }}</div>
                                                        @if (!empty($row['shokokai_code']))
                                                            <div class="muted" style="margin-top:6px; font-size:12px;">商工会コード: {{ $row['shokokai_code'] }}</div>
                                                        @endif
                                                        @if (!empty($row['url_warning']))
                                                            <div style="margin-top:6px; color:var(--danger); font-size:12px;">{{ $row['url_warning'] }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div>{{ $row['postal_code'] ?? '-' }}</div>
                                                        <div style="margin-top:6px;">{{ $row['address'] ?? '-' }}</div>
                                                        <div class="muted" style="margin-top:6px; font-size:12px;">TEL: {{ $row['tel'] ?? '-' }}</div>
                                                        <div class="muted" style="margin-top:4px; font-size:12px;">FAX: {{ $row['fax'] ?? '-' }}</div>
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

                        @if ($invalidRows->isNotEmpty())
                            <details class="help-panel" style="margin-top:18px;">
                                <summary>URL要確認の候補を見る（{{ number_format($invalidRows->count()) }}件）</summary>
                                <div class="help-body">
                                    @foreach ($invalidRows as $row)
                                        <div style="padding:8px 0; border-bottom:1px solid #e4e7ec;">
                                            <div style="font-weight:900;">{{ $row['organization_name'] ?? '-' }}</div>
                                            <div style="word-break:break-all; color:var(--danger);">{{ $row['raw_href'] ?? $row['url'] ?? '-' }}</div>
                                            <div class="muted" style="font-size:12px; margin-top:4px;">{{ $row['url_warning'] ?? 'URL要確認' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif

                        <div class="form-actions sticky-ish" style="margin-top:18px;">
                            <button class="button" type="submit" onclick="return confirm('選択した商工会HPを名簿元候補としてsource_recordsへ保存する？営業先companyは自動作成しない。');">選択分をsource_recordsへ保存</button>
                            <a class="button light" href="{{ route('directory-sources.shokokai-web-search') }}">プレビュー破棄</a>
                        </div>
                    @endif
                </form>

                @if (!empty($excluded))
                    <details class="help-panel" style="margin-top:22px;">
                        <summary>除外された検索結果を見る（{{ number_format(count($excluded)) }}件）</summary>
                        <div class="help-body">
                            @foreach ($excluded as $item)
                                <div style="padding:8px 0; border-bottom:1px solid #e4e7ec;">
                                    <div style="font-weight:800; word-break:break-all;">{{ $item['organization_name'] ?? $item['url'] ?? '-' }}</div>
                                    <div class="muted" style="font-size:12px; margin-top:4px;">{{ $item['excluded_reason'] ?? '-' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
            </section>
        @endif
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
                if (!checkbox.disabled) {
                    checkbox.checked = checked;
                }
            });
        });
    </script>
@endsection
