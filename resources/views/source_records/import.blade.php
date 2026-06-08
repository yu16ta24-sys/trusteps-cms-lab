@extends('layouts.app', ['title' => 'CSV取り込み | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="muted" style="margin:0;">Phase0-4 / CSV取り込み</p>
                    <h1 style="margin:6px 0 0;">source_records CSV取り込み</h1>
                </div>
                <a class="button light" href="{{ route('source-records.index') }}">一覧へ戻る</a>
            </div>

            @if ($errors->any())
                <div class="error" style="margin-top:20px;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('source-records.import.store') }}" enctype="multipart/form-data" style="margin-top:24px;">
                @csrf

                <div class="field">
                    <label for="default_source_type">default_source_type *</label>
                    <input id="default_source_type" name="default_source_type" type="text" value="{{ old('default_source_type', 'csv_import') }}" required>
                    <p class="muted">CSV側にsource_type列がない場合、この値が使われる。</p>
                </div>

                <div class="field">
                    <label for="csv_file">CSVファイル *</label>
                    <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv" required>
                    <p class="muted">UTF-8 / Shift_JIS(CP932) に対応。1行目はヘッダー行。</p>
                </div>

                <button class="button" type="submit">取り込む</button>
            </form>

            <div class="card" style="box-shadow:none; margin-top:24px;">
                <h2 style="margin-top:0;">対応ヘッダー例</h2>
                <p class="muted">完全一致でなくても、以下の名前を拾う。</p>
                <pre>company_name, source_url, phone, pref, city, corporate_number, source_type

日本語例：
会社名, ホームページ, 電話番号, 都道府県, 市区町村, 法人番号</pre>
            </div>
        </section>
    </main>
@endsection
