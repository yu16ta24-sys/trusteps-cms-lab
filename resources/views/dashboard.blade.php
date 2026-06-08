@extends('layouts.app', ['title' => 'ダッシュボード | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <p class="muted" style="margin-top:0;">Phase0 / 研究MVP</p>
            <h1 style="margin-top:0;">ダッシュボード</h1>
            <p>ログイン、DBスキーマ、マスターSeeder、source_records取り込みまで完了。次はsource_recordsからcompaniesを手動生成する。</p>

            <div class="actions" style="margin:24px 0;">
                <a class="button" href="{{ route('source-records.index') }}">source_recordsを見る</a>
                <a class="button light" href="{{ route('source-records.create') }}">source_record手動登録</a>
                <a class="button light" href="{{ route('source-records.import') }}">CSV取り込み</a>
                <a class="button light" href="{{ route('companies.index') }}">companiesを見る</a>
            </div>

            <div class="grid" style="margin-top:24px;">
                <div class="mini-card">
                    <strong>Phase0-1</strong>
                    <span class="muted">Laravel初期構成・ログイン確認</span>
                </div>
                <div class="mini-card">
                    <strong>Phase0-2</strong>
                    <span class="muted">研究MVP DB migration</span>
                </div>
                <div class="mini-card">
                    <strong>Phase0-3</strong>
                    <span class="muted">マスターSeeder</span>
                </div>
                <div class="mini-card">
                    <strong>Phase0-4</strong>
                    <span class="muted">source_records 取り込み基盤</span>
                </div>
                <div class="mini-card">
                    <strong>Phase0-5</strong>
                    <span class="muted">companies生成・手動名寄せ入口</span>
                </div>
            </div>
        </section>
    </main>
@endsection
