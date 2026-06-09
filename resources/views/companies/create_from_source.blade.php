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

                    @php
                        $selectedMunicipalityId = old('municipality_id', $defaults['municipality_id']);
                        $selectedMunicipality = $municipalities->firstWhere('id', (int) $selectedMunicipalityId);
                        $selectedPrefectureId = old('prefecture_filter_id', $selectedMunicipality?->prefecture_id);
                        $prefecturesForFilter = $municipalities
                            ->pluck('prefecture')
                            ->filter()
                            ->unique('id')
                            ->sortBy('id')
                            ->values();
                    @endphp

                    <div class="field">
                        <label for="prefecture_filter_id">都道府県で絞り込み</label>
                        <select id="prefecture_filter_id" name="prefecture_filter_id">
                            <option value="">すべて</option>
                            @foreach ($prefecturesForFilter as $prefecture)
                                <option value="{{ $prefecture->id }}" @selected((string) $selectedPrefectureId === (string) $prefecture->id)>
                                    {{ $prefecture->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="muted" style="font-size:12px; margin:6px 0 0;">
                            全国マスタが増えても探しやすいよう、先に都道府県で絞る。
                        </p>
                    </div>

                    <div class="field">
                        <label for="municipality_id">地域（市区町村マスタ）</label>
                        <select id="municipality_id" name="municipality_id">
                            <option value="">未設定</option>
                            @foreach ($municipalities as $municipality)
                                <option
                                    value="{{ $municipality->id }}"
                                    data-prefecture-id="{{ $municipality->prefecture_id }}"
                                    @selected((string) $selectedMunicipalityId === (string) $municipality->id)
                                >
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

                <div class="actions" style="margin-top:18px;">
                    <button class="button" type="submit" name="after_action" value="company">companyを作成する</button>
                    <button class="button light" type="submit" name="after_action" value="next_source">作成して次の未リンクへ</button>
                </div>
                <p class="muted" style="font-size:12px; margin-top:8px;">
                    連続処理するときは「作成して次の未リンクへ」を使う。新規ルートは使わず、既存のsource_record詳細へ移動するだけ。
                </p>
            </form>
        </section>
    </main>

    <script>
        (() => {
            const prefSelect = document.getElementById('prefecture_filter_id');
            const municipalitySelect = document.getElementById('municipality_id');
            if (!prefSelect || !municipalitySelect) return;

            const allOptions = Array.from(municipalitySelect.options).map((option) => ({
                value: option.value,
                text: option.text,
                prefId: option.dataset.prefectureId || '',
                selected: option.selected,
            }));

            const rebuildMunicipalityOptions = () => {
                const selectedPrefId = prefSelect.value;
                const currentValue = municipalitySelect.value;

                municipalitySelect.innerHTML = '';

                allOptions.forEach((item) => {
                    if (item.value !== '' && selectedPrefId !== '' && item.prefId !== selectedPrefId) {
                        return;
                    }

                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = item.text;
                    if (item.prefId) {
                        option.dataset.prefectureId = item.prefId;
                    }

                    if (item.value === currentValue) {
                        option.selected = true;
                    }

                    municipalitySelect.appendChild(option);
                });

                const currentStillExists = Array.from(municipalitySelect.options).some((option) => option.value === currentValue);
                if (!currentStillExists) {
                    municipalitySelect.value = '';
                }
            };

            prefSelect.addEventListener('change', rebuildMunicipalityOptions);
            rebuildMunicipalityOptions();
        })();
    </script>
@endsection
