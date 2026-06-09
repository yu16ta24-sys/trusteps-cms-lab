@extends('layouts.app', ['title' => 'ダッシュボード | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row" style="gap:18px; align-items:flex-start;">
                <div>
                    <p class="muted" style="margin-top:0; font-weight:800;">Phase1 / 研究MVP</p>
                    <h1 style="margin:0;">ダッシュボード</h1>
                    <p class="muted" style="margin-bottom:0;">実データ投入、company化、4軸採点、営業候補抽出の進捗をざっくり確認する画面。</p>
                </div>
                <div class="actions">
                    <a class="button" href="{{ route('source-records.index') }}">source_records</a>
                    <a class="button light" href="{{ route('companies.index') }}">companies</a>
                    <a class="button light" href="{{ route('companies.candidates') }}">営業候補</a>
                </div>
            </div>
        </section>

        <section class="card" style="margin-top:18px;">
            <div class="row" style="align-items:flex-end;">
                <div>
                    <h2 style="margin:0;">投入・整理状況</h2>
                    <p class="muted" style="margin-bottom:0;">source_recordsからcompany化までの詰まりを見る。</p>
                </div>
            </div>

            <div class="grid" style="margin-top:18px;">
                <div class="mini-card">
                    <div class="muted" style="font-weight:800;">source_records</div>
                    <div style="font-size:34px; font-weight:900; margin-top:8px;">{{ number_format($summary['source_records']['total']) }}</div>
                    <div class="muted">全投入データ</div>
                </div>
                <div class="mini-card">
                    <div class="muted" style="font-weight:800;">未リンク</div>
                    <div style="font-size:34px; font-weight:900; margin-top:8px;">{{ number_format($summary['source_records']['unlinked']) }}</div>
                    <div class="actions" style="justify-content:flex-start; margin-top:12px;">
                        <a class="button small light" href="{{ route('source-records.index', ['link_status' => 'unlinked']) }}">未リンクを見る</a>
                    </div>
                </div>
                <div class="mini-card">
                    <div class="muted" style="font-weight:800;">company</div>
                    <div style="font-size:34px; font-weight:900; margin-top:8px;">{{ number_format($summary['companies']['total']) }}</div>
                    <div class="muted">active {{ number_format($summary['companies']['active']) }} / killed {{ number_format($summary['companies']['killed']) }} / merged {{ number_format($summary['companies']['merged']) }}</div>
                </div>
            </div>
        </section>

        <section class="card" style="margin-top:18px;">
            <div class="row" style="align-items:flex-end;">
                <div>
                    <h2 style="margin:0;">4軸採点の進捗</h2>
                    <p class="muted" style="margin-bottom:0;">自動提案と手動補正の運用状況を見る。</p>
                </div>
                <div class="actions">
                    <a class="button small light" href="{{ route('companies.index', ['score_state' => 'unscored']) }}">未採点</a>
                    <a class="button small light" href="{{ route('companies.index', ['score_state' => 'partial']) }}">一部採点</a>
                    <a class="button small light" href="{{ route('companies.index', ['score_state' => 'manual_adjusted']) }}">手動補正あり</a>
                </div>
            </div>

            <div class="grid" style="margin-top:18px;">
                <div class="mini-card">
                    <span class="badge gray">未採点</span>
                    <div style="font-size:32px; font-weight:900; margin-top:10px;">{{ number_format($summary['scores']['unscored']) }}</div>
                </div>
                <div class="mini-card">
                    <span class="badge blue">一部採点</span>
                    <div style="font-size:32px; font-weight:900; margin-top:10px;">{{ number_format($summary['scores']['partial']) }}</div>
                </div>
                <div class="mini-card">
                    <span class="badge green">4軸採点済み</span>
                    <div style="font-size:32px; font-weight:900; margin-top:10px;">{{ number_format($summary['scores']['fully_scored']) }}</div>
                </div>
                <div class="mini-card">
                    <span class="badge blue">auto提案あり</span>
                    <div style="font-size:32px; font-weight:900; margin-top:10px;">{{ number_format($summary['scores']['has_auto_suggestion']) }}</div>
                    <p class="muted" style="margin-bottom:0;">提案どおり {{ number_format($summary['scores']['suggestion_as_is']) }} / 補正 {{ number_format($summary['scores']['manual_adjusted']) }}</p>
                </div>
            </div>
        </section>

        <section class="card" style="margin-top:18px;">
            <div class="row" style="align-items:flex-end;">
                <div>
                    <h2 style="margin:0;">営業候補の状態</h2>
                    <p class="muted" style="margin-bottom:0;">未kill・未mergedの候補母集団から、推奨候補と採点待ちを見る。</p>
                </div>
                <div class="actions">
                    <a class="button small" href="{{ route('companies.candidates', ['preset' => 'recommended']) }}">推奨候補</a>
                    <a class="button small light" href="{{ route('companies.candidates', ['preset' => 'needs_scoring']) }}">採点待ち</a>
                </div>
            </div>

            <div class="grid" style="margin-top:18px;">
                <div class="mini-card">
                    <div class="muted" style="font-weight:800;">active候補</div>
                    <div style="font-size:34px; font-weight:900; margin-top:8px;">{{ number_format($summary['candidates']['total']) }}</div>
                </div>
                <div class="mini-card">
                    <div class="muted" style="font-weight:800;">推奨</div>
                    <div style="font-size:34px; font-weight:900; margin-top:8px;">{{ number_format($summary['candidates']['recommended']) }}</div>
                    <div class="muted">高機会・低リスク</div>
                </div>
                <div class="mini-card">
                    <div class="muted" style="font-weight:800;">高機会</div>
                    <div style="font-size:34px; font-weight:900; margin-top:8px;">{{ number_format($summary['candidates']['high_opportunity']) }}</div>
                </div>
                <div class="mini-card">
                    <div class="muted" style="font-weight:800;">採点待ち</div>
                    <div style="font-size:34px; font-weight:900; margin-top:8px;">{{ number_format($summary['candidates']['needs_scoring']) }}</div>
                </div>
            </div>
        </section>
    </main>
@endsection
