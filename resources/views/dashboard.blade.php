@extends('layouts.app', ['title' => 'ダッシュボード | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <p class="muted" style="margin-top:0;">Phase0 / 研究MVP</p>
            <h1 style="margin-top:0;">ダッシュボード</h1>
            <p>ログイン機能は動作中。次は、研究MVP用のマスターDBと管理画面の土台を作る。</p>

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
            </div>
        </section>
    </main>
@endsection
