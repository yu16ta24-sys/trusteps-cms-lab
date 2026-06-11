@extends('layouts.app')

@section('title', 'BIZMAPSインポート')

@section('content')
<div class="content">

  {{-- ページヘッダー --}}
  <div style="margin-bottom:28px;">
    <p class="page-kicker">データ収集</p>
    <h1 class="page-title">BIZMAPS インポート</h1>
    <p class="page-subtitle">BIZMAPSから企業情報を収集してsource_recordsに保存します。都道府県・市区町村・業種で絞り込みができます。</p>
  </div>

  <form method="POST" action="{{ route('bizmaps.preview') }}" id="importForm">
    @csrf

    <div style="display:grid;gap:18px;">

      {{-- エリア選択 --}}
      <div class="form-section">
        <div class="form-section-head">
          <div>
            <p class="section-label">Step 1</p>
            <h2 class="form-section-title">エリア選択</h2>
            <p class="form-section-copy">都道府県を選択すると市区町村が表示されます。市区町村を複数選択して絞り込めます。</p>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:200px 1fr;gap:20px;align-items:start;">
          <div class="field required">
            <label for="prefectureSelect">都道府県</label>
            <select name="prefecture_id" id="prefectureSelect" required>
              <option value="">選択してください</option>
              @foreach($prefectures as $pref)
                <option value="{{ $pref->id }}">{{ $pref->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="field">
            <label>市区町村 <span style="font-weight:400;color:var(--muted);font-size:12px;">複数選択可</span></label>
            <div style="display:flex;gap:8px;margin-bottom:8px;">
              <button type="button" class="button small light" id="selectAllCities">全選択</button>
              <button type="button" class="button small light" id="clearCities">クリア</button>
              <span id="citySelectedCount" style="display:none;align-self:center;font-size:12px;color:var(--muted);font-weight:700;"></span>
            </div>
            <div id="cityCheckboxes" style="border:1px solid #d9e2ee;border-radius:14px;padding:12px;max-height:180px;overflow-y:auto;min-height:52px;background:rgba(255,255,255,.9);">
              <span style="color:var(--muted);font-size:13px;">都道府県を選択してください</span>
            </div>
          </div>
        </div>
      </div>

      {{-- 業種選択 --}}
      <div class="form-section">
        <div class="form-section-head">
          <div>
            <p class="section-label">Step 2</p>
            <h2 class="form-section-title">業種選択 <span style="font-size:14px;font-weight:600;color:var(--muted);">任意</span></h2>
            <p class="form-section-copy">業種を指定しない場合は全業種が対象になります。</p>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;align-items:start;">
          <div class="field">
            <label>大業種</label>
            <select name="big_ind_id" id="bigIndSelect">
              <option value="">全業種（絞り込まない）</option>
              @foreach($industries as $ind)
                <option value="{{ $ind['big_id'] }}">{{ $ind['big_name'] }}</option>
              @endforeach
            </select>
          </div>

          <div class="field">
            <label>中業種</label>
            <select name="m_ind_id" id="mIndSelect">
              <option value="">大業種を先に選択</option>
            </select>
          </div>

          <div class="field">
            <label>検索軸</label>
            <div id="industryTypeDisplay" style="padding:12px 0;font-size:13px;color:var(--muted);font-weight:700;">
              都道府県全体で検索
            </div>
            <input type="hidden" name="industry_type" id="industryTypeInput" value="pref">
            <input type="hidden" name="industry_id"   id="industryIdInput"   value="">
            <input type="hidden" name="big_ind_name"  id="bigIndNameInput"   value="">
            <input type="hidden" name="m_ind_name"    id="mIndNameInput"     value="">
          </div>
        </div>
      </div>

      {{-- 取得設定 --}}
      <div class="form-section">
        <div class="form-section-head">
          <div>
            <p class="section-label">Step 3</p>
            <h2 class="form-section-title">取得設定</h2>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:200px 1fr auto;gap:20px;align-items:end;">
          <div class="field">
            <label for="limitInput">取得上限件数</label>
            <input type="number" name="limit" id="limitInput" value="50" min="1" max="500">
            <p class="field-hint">最大500件まで</p>
          </div>

          <div class="field">
            <label style="cursor:pointer;display:flex;align-items:center;gap:10px;margin-top:28px;">
              <input type="checkbox" name="fetch_hp" id="fetchHp" value="1"
                style="width:18px;height:18px;cursor:pointer;accent-color:var(--primary);">
              <span>詳細ページからHP URLを取得する</span>
              <span class="badge amber">低速</span>
            </label>
            <p class="field-hint">ONにすると1件あたり約1秒かかります。50件で約50秒。</p>
          </div>

          <div style="padding-bottom:2px;">
            <button type="submit" class="button" id="submitBtn" style="min-width:160px;min-height:46px;font-size:15px;">
              プレビュー取得
            </button>
          </div>
        </div>
      </div>

    </div>
  </form>

</div>

@push('scripts')
<script>
@verbatim
document.addEventListener('DOMContentLoaded', function () {

  const prefSelect   = document.getElementById('prefectureSelect');
  const cityBox      = document.getElementById('cityCheckboxes');
  const cityCount    = document.getElementById('citySelectedCount');
  const bigIndSelect = document.getElementById('bigIndSelect');
  const mIndSelect   = document.getElementById('mIndSelect');
  const indTypeInput = document.getElementById('industryTypeInput');
  const indIdInput   = document.getElementById('industryIdInput');
  const indTypeDisp  = document.getElementById('industryTypeDisplay');
  const submitBtn    = document.getElementById('submitBtn');

  prefSelect.addEventListener('change', function () {
    const prefId = this.value;
    cityBox.innerHTML = '<span style="color:var(--muted);font-size:13px;">読み込み中...</span>';
    cityCount.style.display = 'none';

    if (!prefId) {
      cityBox.innerHTML = '<span style="color:var(--muted);font-size:13px;">都道府県を選択してください</span>';
      updateIndustryType();
      return;
    }

    fetch(`/bizmaps/municipalities?prefecture_id=${prefId}`)
      .then(r => r.json())
      .then(cities => {
        if (cities.length === 0) {
          cityBox.innerHTML = '<span style="color:var(--muted);font-size:13px;">データがありません</span>';
          return;
        }
        cityBox.innerHTML = cities.map(c => `
          <label style="display:inline-flex;align-items:center;gap:5px;margin:3px 8px 3px 0;font-size:13px;cursor:pointer;font-weight:600;">
            <input class="city-check" type="checkbox" name="city_codes[]" value="${c.code}"
              style="accent-color:var(--primary);width:14px;height:14px;">
            ${c.name}
          </label>
        `).join('');
        updateCityCount();
        updateIndustryType();
        cityBox.querySelectorAll('.city-check').forEach(cb => cb.addEventListener('change', () => { updateCityCount(); updateIndustryType(); }));
      })
      .catch(() => {
        cityBox.innerHTML = '<span style="color:var(--danger);font-size:13px;">取得失敗</span>';
      });
  });

  document.getElementById('selectAllCities').addEventListener('click', function () {
    cityBox.querySelectorAll('.city-check').forEach(cb => cb.checked = true);
    updateCityCount(); updateIndustryType();
  });
  document.getElementById('clearCities').addEventListener('click', function () {
    cityBox.querySelectorAll('.city-check').forEach(cb => cb.checked = false);
    updateCityCount(); updateIndustryType();
  });

  function updateCityCount() {
    const checked = cityBox.querySelectorAll('.city-check:checked').length;
    if (checked > 0) {
      cityCount.textContent = checked + '件選択中';
      cityCount.style.display = 'inline';
    } else {
      cityCount.style.display = 'none';
    }
  }

  const bigIndNameInput = document.getElementById('bigIndNameInput');
  const mIndNameInput   = document.getElementById('mIndNameInput');

  bigIndSelect.addEventListener('change', function () {
    const bigId = this.value;
    mIndSelect.innerHTML = '<option value="">読み込み中...</option>';
    if (!bigId) {
      mIndSelect.innerHTML = '<option value="">大業種を先に選択</option>';
      updateIndustryType();
      return;
    }
    fetch(`/bizmaps/sub-industries?big_ind_id=${bigId}`)
      .then(r => r.json())
      .then(subs => {
        mIndSelect.innerHTML = '<option value="">全て（大業種のみ）</option>';
        subs.forEach(s => { mIndSelect.innerHTML += `<option value="${s.id}">${s.name}</option>`; });
        updateIndustryType();
      });
  });

  mIndSelect.addEventListener('change', updateIndustryType);

  function updateIndustryType() {
    const prefId  = prefSelect.value;
    const checked = cityBox.querySelectorAll('.city-check:checked').length;
    const bigId   = bigIndSelect.value;
    const mIndId  = mIndSelect.value;
    const bigOpt  = bigIndSelect.selectedOptions[0];
    const mOpt    = mIndSelect.selectedOptions[0];

    if (mIndId) {
      indTypeInput.value = 'm_ind'; indIdInput.value = mIndId;
      if (bigIndNameInput) bigIndNameInput.value = bigOpt?.text || '';
      if (mIndNameInput)   mIndNameInput.value   = mOpt?.text  || '';
      indTypeDisp.textContent = '中業種で絞り込み';
    } else if (bigId) {
      indTypeInput.value = 'big_ind'; indIdInput.value = bigId;
      if (bigIndNameInput) bigIndNameInput.value = bigOpt?.text || '';
      if (mIndNameInput)   mIndNameInput.value   = '';
      indTypeDisp.textContent = '大業種で絞り込み';
    } else if (checked > 0) {
      indTypeInput.value = 'city'; indIdInput.value = '';
      if (bigIndNameInput) bigIndNameInput.value = '';
      if (mIndNameInput)   mIndNameInput.value   = '';
      indTypeDisp.textContent = `市区町村 ${checked}件で検索`;
    } else if (prefId) {
      indTypeInput.value = 'pref'; indIdInput.value = '';
      if (bigIndNameInput) bigIndNameInput.value = '';
      if (mIndNameInput)   mIndNameInput.value   = '';
      indTypeDisp.textContent = '都道府県全体で検索';
    } else {
      indTypeInput.value = 'pref'; indIdInput.value = '';
      indTypeDisp.textContent = '都道府県を選択してください';
    }
  }

  document.getElementById('importForm').addEventListener('submit', function () {
    submitBtn.disabled = true;
    submitBtn.textContent = '取得中...';
  });

});
@endverbatim
</script>
@endpush
@endsection
