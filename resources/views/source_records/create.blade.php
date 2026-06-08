@extends('layouts.app', ['title' => 'source_record手動登録 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="muted" style="margin:0;">Phase0-4 / 手動登録</p>
                    <h1 style="margin:6px 0 0;">source_record 手動登録</h1>
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

            <form method="POST" action="{{ route('source-records.store') }}" style="margin-top:24px;">
                @csrf

                <div class="grid">
                    <div class="field">
                        <label for="source_type">source_type *</label>
                        <input id="source_type" name="source_type" type="text" value="{{ old('source_type', 'manual') }}" required>
                    </div>

                    <div class="field">
                        <label for="company_name">会社名・屋号 *</label>
                        <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" required>
                    </div>

                    <div class="field">
                        <label for="source_url">HP URL</label>
                        <input id="source_url" name="source_url" type="url" value="{{ old('source_url') }}" placeholder="https://example.com">
                    </div>

                    <div class="field">
                        <label for="corporate_number">法人番号</label>
                        <input id="corporate_number" name="corporate_number" type="text" value="{{ old('corporate_number') }}">
                    </div>

                    <div class="field">
                        <label for="phone">電話番号</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone') }}">
                    </div>

                    <div class="field">
                        <label for="pref">都道府県</label>
                        <input id="pref" name="pref" type="text" value="{{ old('pref') }}" placeholder="長野県">
                    </div>

                    <div class="field">
                        <label for="city">市区町村</label>
                        <input id="city" name="city" type="text" value="{{ old('city') }}" placeholder="松本市">
                    </div>
                </div>

                <div class="field">
                    <label for="memo">メモ</label>
                    <textarea id="memo" name="memo">{{ old('memo') }}</textarea>
                </div>

                <button class="button" type="submit">登録する</button>
            </form>
        </section>
    </main>
@endsection
