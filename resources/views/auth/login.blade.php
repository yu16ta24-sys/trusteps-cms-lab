@extends('layouts.app', ['title' => 'ログイン | TRUSTEPS CMS Lab'])

@section('content')
    <main class="form-wrap">
        <section class="card">
            <h1 style="margin-top:0;">TRUSTEPS CMS Lab</h1>
            <p class="muted">研究MVP 管理画面にログイン</p>

            @if ($errors->any())
                <div class="error">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf

                <div class="field">
                    <label for="email">メールアドレス</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                </div>

                <div class="field">
                    <label for="password">パスワード</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password">
                </div>

                <div class="field row" style="justify-content:flex-start;">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:500; margin:0;">
                        <input type="checkbox" name="remember" value="1">
                        ログイン状態を保持
                    </label>
                </div>

                <button class="button" type="submit" style="width:100%;">ログイン</button>
            </form>
        </section>
    </main>
@endsection
