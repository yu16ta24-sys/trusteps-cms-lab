@extends('layouts.app', ['title' => 'CSV取り込み | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="muted" style="margin:0;">Phase1-1 / 実データ投入フロー</p>
                    <h1 style="margin:6px 0 0;">source_records CSV取り込み</h1>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('source-records.import.template') }}">テンプレートCSV</a>
                    <a class="button light" href="{{ route('source-records.index') }}">一覧へ戻る</a>
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

            @php
                $summary = $previewResult ?? session('import_summary');
            @endphp

            @if ($summary)
                <div class="card" style="box-shadow:none; margin-top:24px; border-color:#bfdbfe; background:#eff6ff;">
                    <div class="row">
                        <div>
                            <p class="muted" style="margin:0;">{{ ($summary['mode'] ?? '') === 'preview' ? 'プレビュー結果' : '取込結果' }}</p>
                            <h2 style="margin:6px 0 0;">{{ $summary['file_name'] ?? 'CSV' }}</h2>
                        </div>
                        <span class="badge blue">encoding: {{ $summary['detected_encoding'] ?? '-' }}</span>
                    </div>

                    <div class="grid" style="margin-top:18px;">
                        <div class="mini-card"><div class="muted">読取行</div><strong>{{ number_format($summary['total_rows'] ?? 0) }}</strong></div>
                        <div class="mini-card"><div class="muted">有効行</div><strong>{{ number_format($summary['valid_rows'] ?? 0) }}</strong></div>
                        <div class="mini-card"><div class="muted">登録</div><strong>{{ number_format($summary['imported'] ?? 0) }}</strong></div>
                        <div class="mini-card"><div class="muted">スキップ</div><strong>{{ number_format($summary['skipped'] ?? 0) }}</strong></div>
                        <div class="mini-card"><div class="muted">URLあり</div><strong>{{ number_format($summary['with_domain'] ?? 0) }}</strong></div>
                        <div class="mini-card"><div class="muted">URLなし</div><strong>{{ number_format($summary['without_url'] ?? 0) }}</strong></div>
                        <div class="mini-card"><div class="muted">電話あり</div><strong>{{ number_format($summary['with_phone'] ?? 0) }}</strong></div>
                        <div class="mini-card"><div class="muted">重複候補</div><strong>{{ number_format($summary['duplicate_hints'] ?? 0) }}</strong></div>
                    </div>

                    @if (!empty($summary['errors']))
                        <div class="error" style="margin-top:18px;">
                            <strong>エラー / スキップ理由</strong>
                            @foreach ($summary['errors'] as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($summary['warnings']))
                        <div class="card" style="box-shadow:none; margin-top:18px; background:#fffbeb; border-color:#fde68a;">
                            <strong>注意</strong>
                            @foreach ($summary['warnings'] as $warning)
                                <div>{{ $warning }}</div>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($summary['samples']))
                        <h3>先頭サンプル</h3>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>行</th>
                                    <th>会社名</th>
                                    <th>source_type</th>
                                    <th>HP URL</th>
                                    <th>取得元</th>
                                    <th>地域</th>
                                    <th>domain</th>
                                    <th>重複候補</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($summary['samples'] as $sample)
                                    <tr>
                                        <td>{{ $sample['row_number'] }}</td>
                                        <td>{{ $sample['company_name'] ?? '-' }}</td>
                                        <td>{{ $sample['source_type'] ?? '-' }}</td>
                                        <td style="overflow-wrap:anywhere;">{{ $sample['source_url'] ?? '-' }}</td>
                                        <td>
                                            <div>{{ $sample['source_name'] ?? '-' }}</div>
                                            <div class="muted" style="overflow-wrap:anywhere;">{{ $sample['source_page_url'] ?? '-' }}</div>
                                        </td>
                                        <td>{{ $sample['pref'] ?? '-' }} / {{ $sample['city'] ?? '-' }}</td>
                                        <td>{{ $sample['normalized_domain'] ?? '-' }}</td>
                                        <td>
                                            @forelse (($sample['duplicate_signals'] ?? []) as $signal)
                                                <div class="badge gray">{{ $signal }}</div>
                                            @empty
                                                <span class="muted">-</span>
                                            @endforelse
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('source-records.import.store') }}" enctype="multipart/form-data" style="margin-top:24px;">
                @csrf

                <div class="grid">
                    <div class="field">
                        <label for="default_source_type">default_source_type *</label>
                        <input id="default_source_type" name="default_source_type" type="text" value="{{ old('default_source_type', 'csv_import') }}" required>
                        <p class="muted">CSV側にsource_type列がない場合、この値を使う。</p>
                    </div>

                    <div class="field">
                        <label for="csv_file">CSVファイル *</label>
                        <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv" required>
                        <p class="muted">UTF-8 / Shift_JIS(CP932) に対応。1行目はヘッダー行。</p>
                    </div>
                </div>

                <label style="display:flex; align-items:center; gap:10px; font-weight:800; margin:8px 0 20px;">
                    <input type="checkbox" name="dry_run" value="1" @checked(old('dry_run', '1'))>
                    まずプレビューだけ実行する（DBには登録しない）
                </label>

                <div class="actions" style="justify-content:flex-start;">
                    <button class="button" type="submit">CSVを確認 / 取り込む</button>
                    <a class="button light" href="{{ route('source-records.import.template') }}">テンプレートをダウンロード</a>
                </div>
            </form>

            <div class="card" style="box-shadow:none; margin-top:24px;">
                <h2 style="margin-top:0;">推奨ヘッダー</h2>
                <p class="muted">Phase1では、取得元情報と会社HPを分けて残す。raw_jsonにCSVの元行を丸ごと保存する。</p>
                <pre>{{ implode(',', $templateHeaders ?? []) }}</pre>
            </div>

            <div class="card" style="box-shadow:none; margin-top:16px;">
                <h2 style="margin-top:0;">最低限必要な列</h2>
                <p class="muted">最低限、会社名だけあれば登録可能。ただし、HP URLがない行は後工程のcompany化・domain登録で使いにくい。</p>
                <pre>raw_name, raw_url, pref, city

旧形式も一部対応：
company_name, source_url, phone, pref, city, corporate_number</pre>
            </div>
        </section>
    </main>
@endsection
