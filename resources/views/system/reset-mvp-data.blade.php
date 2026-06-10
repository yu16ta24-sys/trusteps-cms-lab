@extends('layouts.app')

@section('content')
    @php
        $stage = $stage ?? 'start';
        $counts = $counts ?? [];
        $total = $total ?? 0;
        $token = $token ?? null;
    @endphp

    <main class="content">
        <div class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">system maintenance</p>
                    <h1 class="page-title">MVPデータリセット</h1>
                    <p class="page-subtitle">
                        本稼働前に、MVP期間中の候補会社・source_records・解析結果・営業判定だけを削除する。
                        users / migration履歴 / マスタ / 業界スコア設定は残す。
                    </p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('dashboard') }}">Dashboardへ戻る</a>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="status" style="margin-top:18px;">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="error" style="margin-top:18px;">
                <strong>エラー</strong>
                <ul style="margin:8px 0 0 20px; padding:0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid" style="margin-top:18px;">
            <div class="mini-card">
                <p class="section-label">delete target</p>
                <div style="font-size:34px; font-weight:950; margin-top:6px;">{{ number_format($total) }}</div>
                <p class="muted" style="margin:6px 0 0;">現在の削除対象件数</p>
            </div>
            <div class="mini-card">
                <p class="section-label">kept data</p>
                <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:8px;">
                    <span class="badge green">users</span>
                    <span class="badge green">migrations</span>
                    <span class="badge green">pref/municipality</span>
                    <span class="badge green">industries</span>
                    <span class="badge green">industry scores</span>
                </div>
                <p class="muted" style="margin:10px 0 0;">ログイン情報や設定系は残す。</p>
            </div>
            <div class="mini-card">
                <p class="section-label">flow</p>
                <div style="margin-top:10px; line-height:1.8; font-weight:850;">
                    押す → 件数出る → 本当に良い？ → マジで良い？ → 実行
                </div>
                <p class="muted" style="margin:8px 0 0;">誤クリック防止のため、3段階確認。</p>
            </div>
        </div>

        <div class="card" style="margin-top:18px;">
            <div class="row">
                <div>
                    <p class="section-label">reset targets</p>
                    <h2 style="margin:4px 0 0;">削除対象テーブル</h2>
                </div>
                <span class="badge red">本番前専用</span>
            </div>

            <div class="table-wrap" style="margin-top:16px;">
                <table>
                    <thead>
                    <tr>
                        <th>対象</th>
                        <th>テーブル</th>
                        <th>件数</th>
                        <th>内容</th>
                        <th>状態</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($counts as $row)
                        <tr>
                            <td><strong>{{ $row['label'] }}</strong></td>
                            <td><code>{{ $row['table'] }}</code></td>
                            <td>
                                @if ($row['exists'] && $row['error'] === null)
                                    <strong>{{ number_format((int) $row['count']) }}</strong>
                                @elseif (!$row['exists'])
                                    <span class="muted">未作成</span>
                                @else
                                    <span class="badge red">取得失敗</span>
                                @endif
                            </td>
                            <td class="muted">{{ $row['note'] }}</td>
                            <td>
                                @if (!$row['exists'])
                                    <span class="badge gray">skip</span>
                                @elseif ($row['error'] !== null)
                                    <span class="badge red">error</span>
                                @elseif ((int) $row['count'] > 0)
                                    <span class="badge amber">delete</span>
                                @else
                                    <span class="badge green">empty</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" style="margin-top:18px; border-color:#fecaca; background:#fffafa;">
            @if ($stage === 'start')
                <p class="section-label">step 1</p>
                <h2 style="margin:4px 0 8px;">まず件数を確認する</h2>
                <p class="muted" style="line-height:1.8;">
                    この段階ではまだ削除しない。現在の削除対象件数を確認し、次の確認画面へ進むだけ。
                </p>
                <form method="POST" action="{{ route('system.reset-mvp-data.preview') }}" class="form-actions">
                    @csrf
                    <button class="button danger" type="submit">MVPデータリセットの件数を確認</button>
                </form>
            @elseif ($stage === 'preview')
                <p class="section-label">step 2</p>
                <h2 style="margin:4px 0 8px;">本当に良い？</h2>
                <p style="line-height:1.8; color:#991b1b; font-weight:850;">
                    削除対象は {{ number_format($total) }} 件。ここから先に進んでも、次の最終確認まではまだ削除しない。
                </p>
                <div class="form-actions">
                    <form method="POST" action="{{ route('system.reset-mvp-data.confirm') }}">
                        @csrf
                        <input type="hidden" name="reset_token" value="{{ $token }}">
                        <button class="button danger" type="submit">本当に良い。最終確認へ進む</button>
                    </form>
                    <a class="button light" href="{{ route('system.reset-mvp-data.index') }}">やめる</a>
                </div>
            @elseif ($stage === 'confirm')
                <p class="section-label">step 3</p>
                <h2 style="margin:4px 0 8px; color:#991b1b;">マジで良い？</h2>
                <p style="line-height:1.8; color:#991b1b; font-weight:900;">
                    実行すると、候補会社・source_records・解析結果・営業判定などのMVP実データを削除する。
                    users / マスタ / 業界スコア設定は残るが、この操作自体は画面からは元に戻せない。
                </p>
                <form method="POST" action="{{ route('system.reset-mvp-data.destroy') }}" class="form-actions">
                    @csrf
                    <input type="hidden" name="reset_token" value="{{ $token }}">
                    <label style="display:flex; gap:10px; align-items:center; font-weight:900; color:#991b1b; width:100%;">
                        <input type="checkbox" name="final_confirm" value="1" required>
                        マジで良い。本稼働前のMVPデータとして削除してよい。
                    </label>
                    <button class="button danger" type="submit">マジでリセット実行</button>
                    <a class="button light" href="{{ route('system.reset-mvp-data.index') }}">やめる</a>
                </form>
            @endif
        </div>
    </main>
@endsection
