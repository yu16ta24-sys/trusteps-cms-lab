@extends('layouts.app', ['title' => 'アカウント設定 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <div class="card" style="max-width: 720px; margin: 0 auto;">
            <div class="row" style="margin-bottom: 20px;">
                <div>
                    <h1 style="margin: 0 0 6px;">アカウント設定</h1>
                    <p class="muted" style="margin: 0;">ログイン用メールアドレスとパスワードを変更する。</p>
                </div>
                <a href="{{ route('dashboard') }}" class="button light">Dashboardへ戻る</a>
            </div>

            @if (session('status'))
                <div class="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="error">
                    <strong>入力内容を確認して。</strong>
                    <ul style="margin: 8px 0 0; padding-left: 20px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('account.update') }}">
                @csrf
                @method('PUT')

                <div class="field">
                    <label for="email">ログイン用メールアドレス</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user?->email) }}" required autocomplete="username">
                    <p class="muted" style="margin: 8px 0 0; font-size: 13px;">次回ログインからこのメールアドレスを使う。</p>
                </div>

                <div class="field">
                    <label for="password">新しいパスワード</label>
                    <input id="password" type="password" name="password" autocomplete="new-password" placeholder="変更しない場合は空欄">
                    <p class="muted" style="margin: 8px 0 0; font-size: 13px;">8文字以上。変更しない場合は空欄のままでOK。</p>
                </div>

                <div class="field">
                    <label for="password_confirmation">新しいパスワード（確認）</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" placeholder="新しいパスワードをもう一度">
                </div>

                <div class="field">
                    <label for="current_password">現在のパスワード</label>
                    <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
                    <p class="muted" style="margin: 8px 0 0; font-size: 13px;">安全のため、変更時は現在のパスワード確認が必要。</p>
                </div>

                <div class="actions">
                    <button class="button" type="submit">ログイン情報を更新</button>
                </div>
            </form>
        </div>
    </main>
@endsection
