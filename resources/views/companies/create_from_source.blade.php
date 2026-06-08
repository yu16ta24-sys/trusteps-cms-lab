@extends('layouts.app', ['title' => 'company作成 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="muted" style="margin:0;">Phase0-5 / source_recordからcompany作成</p>
                    <h1 style="margin:6px 0 0;">company作成</h1>
                </div>
                <a class="button light" href="{{ route('source-records.show', $sourceRecord) }}">source_recordへ戻る</a>
            </div>

            @if ($errors->any())
                <div class="error" style="margin-top:20px;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="card" style="box-shadow:none; margin-top:20px;">
                <h2 style="margin-top:0;">元データ</h2>
                <div class="table-wrap">
                    <table>
                        <tbody>
                        <tr><th>source_record_id</th><td>{{ $sourceRecord->id }}</td></tr>
                        <tr><th>source_type</th><td>{{ $sourceRecord->source_type }}</td></tr>
                        <tr><th>name_norm</th><td>{{ $sourceRecord->name_norm ?? '-' }}</td></tr>
                        <tr><th>source_url</th><td style="overflow-wrap:anywhere;">{{ $sourceRecord->source_url ?? '-' }}</td></tr>
                        <tr><th>source pref/city</th><td>{{ $sourceRecord->pref ?? '-' }} / {{ $sourceRecord->city ?? '-' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="POST" action="{{ route('companies.store-from-source', $sourceRecord) }}" style="margin-top:24px;">
                @csrf

                <div class="grid">
                    <div class="field">
                        <label for="status">status *</label>
                        <select id="status" name="status" required>
                            <option value="candidate" @selected(old('status', $defaults['status']) === 'candidate')>candidate</option>
                            <option value="confirmed" @selected(old('status', $defaults['status']) === 'confirmed')>confirmed</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="display_name">表示名・屋号 *</label>
                        <input id="display_name" name="display_name" type="text" value="{{ old('display_name', $defaults['display_name']) }}" required>
                    </div>

                    <div class="field">
                        <label for="legal_name">法人名</label>
                        <input id="legal_name" name="legal_name" type="text" value="{{ old('legal_name', $defaults['legal_name']) }}">
                    </div>

                    <div class="field">
                        <label for="industry_id">業種</label>
                        <select id="industry_id" name="industry_id">
                            <option value="">未設定</option>
                            @foreach ($industries as $industry)
                                <option value="{{ $industry->id }}" @selected((string) old('industry_id', $defaults['industry_id']) === (string) $industry->id)>
                                    {{ $industry->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="municipality_id">地域（市区町村マスタ）</label>
                        <select id="municipality_id" name="municipality_id">
                            <option value="">未設定</option>
                            @foreach ($municipalities as $municipality)
                                <option value="{{ $municipality->id }}" @selected((string) old('municipality_id', $defaults['municipality_id']) === (string) $municipality->id)>
                                    {{ $municipality->prefecture->name }} / {{ $municipality->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="muted" style="font-size:12px; margin:6px 0 0;">
                            基本はここだけ選ぶ。都道府県・市区町村名の手入力は矛盾防止のため非表示。
                        </p>
                    </div>

                    <div class="field">
                        <label for="corporate_number">法人番号</label>
                        <input id="corporate_number" name="corporate_number" type="text" value="{{ old('corporate_number', $defaults['corporate_number']) }}">
                    </div>

                    <input type="hidden" name="pref" value="">
                    <input type="hidden" name="city" value="">

                    <div class="field">
                        <label for="primary_url">公式HP URL</label>
                        <input id="primary_url" name="primary_url" type="text" value="{{ old('primary_url', $defaults['primary_url']) }}">
                    </div>

                    <div class="field">
                        <label for="match_type">match_type *</label>
                        <select id="match_type" name="match_type" required>
                            <option value="manual_new" @selected(old('match_type', $defaults['match_type']) === 'manual_new')>manual_new（新規company作成）</option>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label for="note">判断メモ</label>
                    <textarea id="note" name="note">{{ old('note') }}</textarea>
                    <p class="muted">現時点ではnoteは保存しない。画面上の判断補助だけ。</p>
                </div>

                <button class="button" type="submit">companyを作成する</button>
            </form>
        </section>
    </main>
@endsection
