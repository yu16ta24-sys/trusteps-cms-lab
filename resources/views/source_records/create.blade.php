@extends('layouts.app', ['title' => 'HP未確認手動登録 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">manual intake / source record</p>
                    <h1 class="page-title">HP未確認 手動登録</h1>
                    <p class="page-subtitle">
                        CSVにしづらい単発データを登録する入口。ここでは外部取得の生データとして残し、整形や判断はcompany側で行う。
                    </p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('source-records.import') }}">CSV取り込みへ</a>
                    <a class="button light" href="{{ route('source-records.index') }}">一覧へ戻る</a>
                </div>
            </div>

            <details class="help-panel">
                <summary>手動登録の使いどころ</summary>
                <div class="help-body">
                    <div>1件だけ追加したい時、名簿から拾った情報をすぐ残したい時に使う。</div>
                    <div>source_recordは原典データなので、分からない項目は空欄でOK。後工程でcompany化・採点する。</div>
                </div>
            </details>

            @if ($errors->any())
                <div class="alert-box error" style="margin-top:20px;">
                    <div>
                        <div class="alert-title">入力内容を確認して</div>
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('source-records.store') }}" class="form-shell">
                @csrf

                <div class="form-section">
                    <div class="form-section-head">
                        <div>
                            <p class="section-label">source</p>
                            <h2 class="form-section-title">取得元と基本情報</h2>
                            <p class="form-section-copy">最低限、source_typeと会社名・屋号があれば登録できる。</p>
                        </div>
                    </div>

                    <div class="grid">
                        <div class="field required">
                            <label for="source_type">source_type</label>
                            <input id="source_type" name="source_type" type="text" value="{{ old('source_type', 'manual') }}" required>
                            <p class="field-hint">例：manual / csv_import / public_list</p>
                        </div>

                        <div class="field required">
                            <label for="company_name">会社名・屋号</label>
                            <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" required>
                            <p class="field-hint">外部リストに載っていた表記をそのまま入れる。</p>
                        </div>

                        <div class="field">
                            <label for="source_url">HP URL</label>
                            <input id="source_url" name="source_url" type="url" value="{{ old('source_url') }}" placeholder="https://example.com">
                            <p class="field-hint">公式HPらしきURL。分からなければ空欄でOK。</p>
                        </div>

                        <div class="field">
                            <label for="corporate_number">法人番号</label>
                            <input id="corporate_number" name="corporate_number" type="text" value="{{ old('corporate_number') }}">
                        </div>

                        <div class="field">
                            <label for="phone">電話番号</label>
                            <input id="phone" name="phone" type="text" value="{{ old('phone') }}">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <div>
                            <p class="section-label">region / memo</p>
                            <h2 class="form-section-title">地域とメモ</h2>
                            <p class="form-section-copy">地域は後から絞り込みに使う。メモには取得元の補足や違和感を残す。</p>
                        </div>
                    </div>

                    <div class="grid">
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
                        <textarea id="memo" name="memo" placeholder="取得元、判断保留理由、後で確認したいことなど">{{ old('memo') }}</textarea>
                    </div>

                    <div class="form-actions sticky-ish">
                        <button class="button" type="submit">登録する</button>
                        <a class="button light" href="{{ route('source-records.index') }}">キャンセル</a>
                    </div>
                </div>
            </form>
        </section>
    </main>
@endsection
