@extends('layouts.app', ['title' => 'company作成 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="page-kicker">source record → company</p>
                    <h1 class="page-title">company作成</h1>
                    <p class="page-subtitle">
                        source_recordを正規化された事業者マスタへ変換する。迷う場合は無理に統合せず、新規candidateとして残す。
                    </p>
                </div>
                <a class="button light" href="{{ route('source-records.show', $sourceRecord) }}">source_recordへ戻る</a>
            </div>

            <details class="help-panel">
                <summary>company化の考え方</summary>
                <div class="help-body">
                    <div>companiesは分析・採点・営業候補抽出に使う正規化マスタ。</div>
                    <div>display_nameは画面で見やすい屋号・ブランド名を優先。法人名が別に分かる場合はlegal_nameへ入れる。</div>
                    <div>地域は市区町村マスタを優先し、手入力の都道府県/市区町村は矛盾防止のため使わない。</div>
                </div>
            </details>

            @if ($errors->any())
                <div class="error" style="margin-top:20px;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="info-strip" style="margin-top:20px;">
                <div class="row">
                    <div>
                        <p class="section-label">source</p>
                        <strong>{{ $sourceRecord->name_norm ?? 'source_record #' . $sourceRecord->id }}</strong>
                        <div class="muted" style="margin-top:6px; overflow-wrap:anywhere;">
                            {{ $sourceRecord->source_url ?? '-' }} / {{ $sourceRecord->pref ?? '-' }} / {{ $sourceRecord->city ?? '-' }}
                        </div>
                    </div>
                    <span class="badge gray">source_type：{{ $sourceRecord->source_type }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('companies.store-from-source', $sourceRecord) }}" class="form-shell">
                @csrf

                <div class="form-section">
                    <div class="form-section-head">
                        <div>
                            <p class="section-label">basic information</p>
                            <h2 class="form-section-title">companyの基本情報</h2>
                            <p class="form-section-copy">display_nameは画面で見る屋号・ブランド名。後の採点や候補一覧で中心になる。</p>
                        </div>
                    </div>
                    <div class="grid" style="margin-top:14px;">
                        <div class="field required">
                            <label for="status">status</label>
                            <select id="status" name="status" required>
                                <option value="candidate" @selected(old('status', $defaults['status']) === 'candidate')>candidate</option>
                                <option value="confirmed" @selected(old('status', $defaults['status']) === 'confirmed')>confirmed</option>
                            </select>
                        </div>

                        <div class="field required">
                            <label for="display_name">表示名・屋号</label>
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
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <div>
                            <p class="section-label">region / domain</p>
                            <h2 class="form-section-title">地域・法人番号・公式HP</h2>
                            <p class="form-section-copy">地域は市区町村マスタを優先。公式HPはdomain作成と候補判定の起点になる。</p>
                        </div>
                    </div>
                    <div class="grid" style="margin-top:14px;">
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
                        </div>

                        <div class="field">
                            <label for="corporate_number">法人番号</label>
                            <input id="corporate_number" name="corporate_number" type="text" value="{{ old('corporate_number', $defaults['corporate_number']) }}">
                        </div>

                        <div class="field">
                            <label for="primary_url">公式HP URL</label>
                            <input id="primary_url" name="primary_url" type="text" value="{{ old('primary_url', $defaults['primary_url']) }}">
                        </div>

                        <input type="hidden" name="pref" value="">
                        <input type="hidden" name="city" value="">

                        <div class="field">
                            <label for="match_type">match_type *</label>
                            <select id="match_type" name="match_type" required>
                                <option value="manual_new" @selected(old('match_type', $defaults['match_type']) === 'manual_new')>manual_new（新規company作成）</option>
                            </select>
                        </div>
                    </div>
                </div>

                <details class="help-panel" style="margin-top:18px;">
                    <summary>判断メモを入力する</summary>
                    <div class="help-body">
                        <div class="field" style="margin-bottom:0;">
                            <label for="note">判断メモ</label>
                            <textarea id="note" name="note">{{ old('note') }}</textarea>
                            <p class="muted" style="font-size:12px; margin:6px 0 0;">現時点ではnoteは保存しない。画面上の判断補助だけ。</p>
                        </div>
                    </div>
                </details>

                <div class="form-actions sticky-ish">
                    <button class="button" type="submit" name="after_action" value="company">companyを作成する</button>
                    <button class="button light" type="submit" name="after_action" value="next_source">作成して次の未リンクへ</button>
                </div>
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
                    if (item.value !== '' && selectedPrefId !== '' && item.prefId !== selectedPrefId) return;
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = item.text;
                    if (item.prefId) option.dataset.prefectureId = item.prefId;
                    if (item.value === currentValue) option.selected = true;
                    municipalitySelect.appendChild(option);
                });
                const currentStillExists = Array.from(municipalitySelect.options).some((option) => option.value === currentValue);
                if (!currentStillExists) municipalitySelect.value = '';
            };

            prefSelect.addEventListener('change', rebuildMunicipalityOptions);
            rebuildMunicipalityOptions();
        })();
    </script>
@endsection
