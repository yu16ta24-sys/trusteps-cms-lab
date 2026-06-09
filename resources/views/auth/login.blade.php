@extends('layouts.app', ['title' => 'ログイン | TRUSTEPS CMS Lab'])

@section('content')
    <main class="auth-shell">
        <section class="auth-hero" aria-label="TRUSTEPS CMS Lab">
            <div class="auth-logo">TRUSTEPS CMS Lab</div>

            <div style="position:relative; z-index:1;">
                <p class="page-kicker" style="color:rgba(255,255,255,.72);">Research MVP</p>
                <h1 class="auth-title">営業候補を、<br>感覚じゃなくデータで見る。</h1>
                <p class="auth-copy">
                    source_recordsを整理し、company化し、4軸スコアで候補を見極めるための社内研究ツール。
                </p>
            </div>

            <div class="auth-meta">
                <span>CSV投入</span>
                <span>company整理</span>
                <span>4軸スコア</span>
                <span>候補抽出</span>
            </div>
        </section>

        <section class="card auth-card">
            <p class="page-kicker">Sign in</p>
            <h1>ログイン</h1>
            <p class="muted" style="margin:10px 0 22px; line-height:1.7;">TRUSTEPS CMS Lab の管理画面に入る。</p>

            @if ($errors->any())
                <div class="alert-box error">
                    <div>
                        <div class="alert-title">ログインできなかった</div>
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (session('status'))
                <div class="alert-box status">
                    <div>
                        <div class="alert-title">完了</div>
                        <div>{{ session('status') }}</div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf

                <div class="field">
                    <label for="email">メールアドレス</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="you@example.com">
                </div>

                <div class="field">
                    <label for="password">パスワード</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="password">
                </div>

                <div class="field row" style="justify-content:flex-start; margin-bottom:20px;">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:700; margin:0; color:#475467;">
                        <input type="checkbox" name="remember" value="1">
                        ログイン状態を保持
                    </label>
                </div>

                <button class="button" type="submit" style="width:100%;">ログイン</button>
            </form>
        </section>
    </main>
@endsection
