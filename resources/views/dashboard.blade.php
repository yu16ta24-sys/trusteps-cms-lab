@extends('layouts.app', ['title' => 'ダッシュボード | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row" style="gap:18px; align-items:flex-start;">
                <div>
                    <p class="page-kicker">Phase1 / Research MVP</p>
                    <h1 class="page-title">ダッシュボード</h1>
                    <p class="page-subtitle">今日やる作業と、データ投入・採点・候補抽出の進捗を見る。</p>
                    <details class="help-panel">
                        <summary>この画面の見方</summary>
                        <div class="help-body">source_recordsのcompany化、4軸採点、営業候補確認を1画面で追う。迷ったら「次に処理するもの」→「今日さばくリスト」の順で見る。</div>
                    </details>
                </div>
                <div class="actions">
                    <a class="button" href="{{ route('source-records.index') }}">source_records</a>
                    <a class="button light" href="{{ route('companies.index') }}">companies</a>
                    <a class="button light" href="{{ route('companies.candidates') }}">営業候補</a>
                </div>
            </div>
        </section>

        <section class="card" style="margin-top:18px; border-left:4px solid #2563eb;">
            <div class="row" style="align-items:flex-end;">
                <div>
                    <h2 style="margin:0;">次に処理するもの</h2>
                    <details class="help-panel"><summary>処理順の考え方</summary><div class="help-body">未リンクsource_recordsをcompany化し、未採点companyを4軸採点し、最後に高機会・低リスク候補を確認する。</div></details>
                </div>
            </div>

            <div class="grid" style="margin-top:18px;">
                <div class="mini-card">
                    <span class="badge blue">1. company化待ち</span>
                    <div style="font-size:32px; font-weight:900; margin-top:10px;">{{ number_format($summary['source_records']['unlinked']) }}</div>
                    <p class="muted">未リンクsource_records</p>
                    <div class="actions" style="justify-content:flex-start;">
                        <a class="button small" href="{{ route('source-records.index', ['link_status' => 'unlinked']) }}">処理する</a>
                    </div>
                </div>
                <div class="mini-card">
                    <span class="badge gray">2. 未採点</span>
                    <div style="font-size:32px; font-weight:900; margin-top:10px;">{{ number_format($summary['scores']['unscored']) }}</div>
                    <p class="muted">まだ4軸未入力</p>
                    <div class="actions" style="justify-content:flex-start;">
                        <a class="button small light" href="{{ route('companies.index', ['score_state' => 'unscored']) }}">見る</a>
                    </div>
                </div>
                <div class="mini-card">
                    <span class="badge blue">3. 採点待ち候補</span>
                    <div style="font-size:32px; font-weight:900; margin-top:10px;">{{ number_format($summary['candidates']['needs_scoring']) }}</div>
                    <p class="muted">候補一覧で4軸不足</p>
                    <div class="actions" style="justify-content:flex-start;">
                        <a class="button small light" href="{{ route('companies.candidates', ['preset' => 'needs_scoring']) }}">見る</a>
                    </div>
                </div>
                <div class="mini-card">
                    <span class="badge green">4. 推奨候補</span>
                    <div style="font-size:32px; font-weight:900; margin-top:10px;">{{ number_format($summary['candidates']['recommended']) }}</div>
                    <p class="muted">高機会・低リスク</p>
                    <div class="actions" style="justify-content:flex-start;">
                        <a class="button small" href="{{ route('companies.candidates', ['preset' => 'recommended']) }}">確認する</a>
                    </div>
                </div>
            </div>
        </section>


        <section class="card" style="margin-top:18px; border-left:4px solid #16a34a;">
            <div class="row" style="align-items:flex-end;">
                <div>
                    <p class="muted" style="margin:0; font-weight:800;">v0.16 / 作業ボード</p>
                    <h2 style="margin:6px 0 0;">今日さばくリスト</h2>
                    <details class="help-panel"><summary>作業ボードの使い方</summary><div class="help-body">実際に開くべきsource_record、採点対象、推奨候補を最大5件ずつ表示する。数値サマリーより先に作業したい時はここを使う。</div></details>
                </div>
            </div>

            <div class="grid" style="margin-top:18px; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));">
                <div class="mini-card" style="background:#f0f9ff; border-color:#bae6fd;">
                    <div class="row" style="align-items:center;">
                        <strong>次のsource_records</strong>
                        <span class="badge blue">未リンク</span>
                    </div>
                    <p class="muted" style="margin:8px 0 12px;">まずcompany化する候補。IDが古い順に5件。</p>
                    @forelse ($workBoard['next_source_records'] as $record)
                        @php
                            $rawName = data_get($record->raw_json, 'canonical.raw_name')
                                ?? data_get($record->raw_json, 'company_name')
                                ?? $record->name_norm
                                ?? ('source_record #' . $record->id);
                            $region = trim(($record->pref ?? '') . ' ' . ($record->city ?? ''));
                        @endphp
                        <div style="padding:10px 0; border-top:1px solid rgba(2,132,199,.18);">
                            <div style="font-weight:900;">#{{ $record->id }} {{ $rawName }}</div>
                            <div class="muted" style="font-size:13px; margin-top:2px;">
                                {{ $region !== '' ? $region : '地域未設定' }} / {{ $record->normalized_domain ?: 'domainなし' }}
                            </div>
                            <div class="actions" style="justify-content:flex-start; margin-top:8px;">
                                <a class="button small" href="{{ route('source-records.show', $record) }}">開く</a>
                            </div>
                        </div>
                    @empty
                        <p class="muted" style="margin-bottom:0;">未リンクsource_recordは今のところなし。</p>
                    @endforelse
                </div>

                <div class="mini-card" style="background:#fff7ed; border-color:#fed7aa;">
                    <div class="row" style="align-items:center;">
                        <strong>次の採点対象</strong>
                        <span class="badge gray">4軸不足</span>
                    </div>
                    <p class="muted" style="margin:8px 0 12px;">スコアが足りないcompany。未採点に近い順に5件。</p>
                    @forelse ($workBoard['scoring_queue'] as $company)
                        <div style="padding:10px 0; border-top:1px solid rgba(249,115,22,.18);">
                            <div style="font-weight:900;">#{{ $company->id }} {{ $company->display_name ?? $company->legal_name ?? '名称未設定' }}</div>
                            <div class="muted" style="font-size:13px; margin-top:2px;">採点 {{ $company->dashboard_scored_axes_count }} / 4</div>
                            <div class="actions" style="justify-content:flex-start; margin-top:8px;">
                                <a class="button small light" href="{{ route('companies.show', $company) }}">採点する</a>
                            </div>
                        </div>
                    @empty
                        <p class="muted" style="margin-bottom:0;">採点待ちcompanyは今のところなし。</p>
                    @endforelse
                </div>

                <div class="mini-card" style="background:#f0fdf4; border-color:#bbf7d0;">
                    <div class="row" style="align-items:center;">
                        <strong>推奨候補TOP</strong>
                        <span class="badge green">高機会・低リスク</span>
                    </div>
                    <p class="muted" style="margin:8px 0 12px;">4軸採点済みの中で、優先確認したい候補。</p>
                    @forelse ($workBoard['recommended_queue'] as $company)
                        <div style="padding:10px 0; border-top:1px solid rgba(22,163,74,.18);">
                            <div style="font-weight:900;">#{{ $company->id }} {{ $company->display_name ?? $company->legal_name ?? '名称未設定' }}</div>
                            <div class="muted" style="font-size:13px; margin-top:2px;">
                                機会 {{ $company->dashboard_opportunity_score }} / リスク {{ $company->dashboard_risk_score }}
                            </div>
                            <div class="actions" style="justify-content:flex-start; margin-top:8px;">
                                <a class="button small" href="{{ route('companies.show', $company) }}">詳細</a>
                            </div>
                        </div>
                    @empty
                        <p class="muted" style="margin-bottom:0;">推奨候補はまだなし。4軸採点が増えると出てくる。</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="card" style="margin-top:18px;">
            <div class="row" style="align-items:flex-end;">
                <div>
                    <h2 style="margin:0;">投入・整理状況</h2>
                    <details class="help-panel"><summary>投入・整理状況とは</summary><div class="help-body">CSVや手動で投入したsource_recordsが、どれだけcompany化されたかを見る。未リンクが多い場合は先にcompany化を進める。</div></details>
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
                    <details class="help-panel"><summary>4軸採点の見方</summary><div class="help-body">未採点・一部採点・4軸採点済みの割合を見る。auto提案と手動補正の差分は、今後のスコアロジック改善に使う。</div></details>
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
                    <details class="help-panel"><summary>営業候補の見方</summary><div class="help-body">未kill・未mergedのcompanyを母集団にして、推奨候補・高機会・採点待ちを確認する。まだ営業送信管理ではなく、候補抽出用のビュー。</div></details>
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
