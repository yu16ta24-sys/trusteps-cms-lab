@extends('layouts.app')

@section('title', 'BIZMAPSインポート')

@section('content')
<div class="content">

  {{-- ページヘッダー --}}
  <div style="margin-bottom:28px;">
    <p class="page-kicker">データ収集</p>
    <h1 class="page-title">BIZMAPS インポート</h1>
    <p class="page-subtitle">BIZMAPSから企業情報を収集してsource_recordsに保存します。都道府県・市区町村で絞り込みができます。</p>
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

      {{-- 取得設定 --}}
      <div class="form-section">
        <div class="form-section-head">
          <div>
            <p class="section-label">Step 2</p>
            <h2 class="form-section-title">取得設定</h2>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:180px 1fr auto;gap:20px;align-items:end;">
          <div class="field">
            <label for="limitSelect">取得上限件数</label>
            <select name="limit" id="limitSelect">
              <option value="10">10件</option>
              <option value="25">25件</option>
              <option value="50" selected>50件</option>
              <option value="75">75件</option>
              <option value="100">100件</option>
              <option value="150">150件</option>
              <option value="200">200件</option>
              <option value="300">300件</option>
              <option value="500">500件</option>
            </select>
          </div>

          <div class="field">
            <label style="cursor:pointer;display:flex;align-items:center;gap:10px;margin-top:28px;">
              <input type="checkbox" name="fetch_hp" id="fetchHp" value="1"
                style="width:18px;height:18px;cursor:pointer;accent-color:var(--primary);">
              <span>詳細ページからHP URLを取得する</span>
              <span class="badge amber">低速</span>
            </label>
            <p class="field-hint">ONにすると1件あたり約1秒かかります。</p>
          </div>

          <div style="padding-bottom:2px;">
            <button type="submit" class="button" id="submitBtn" style="min-width:160px;min-height:46px;font-size:15px;">
              プレビュー取得
            </button>
          </div>
        </div>
      </div>

    </div>

    <input type="hidden" name="industry_type" value="pref">
    <input type="hidden" name="industry_id"   value="">
    <input type="hidden" name="big_ind_name"  value="">
    <input type="hidden" name="m_ind_name"    value="">
  </form>

</div>

@push('scripts')
@verbatim
<script>
document.addEventListener('DOMContentLoaded', function () {

  const prefSelect  = document.getElementById('prefectureSelect');
  const cityBox     = document.getElementById('cityCheckboxes');
  const cityCount   = document.getElementById('citySelectedCount');
  const submitBtn   = document.getElementById('submitBtn');

  prefSelect.addEventListener('change', function () {
    const prefId = this.value;
    cityBox.innerHTML = '<span style="color:var(--muted);font-size:13px;">読み込み中...</span>';
    cityCount.style.display = 'none';

    if (!prefId) {
      cityBox.innerHTML = '<span style="color:var(--muted);font-size:13px;">都道府県を選択してください</span>';
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
        cityBox.querySelectorAll('.city-check').forEach(cb => cb.addEventListener('change', updateCityCount));
      })
      .catch(() => {
        cityBox.innerHTML = '<span style="color:var(--danger);font-size:13px;">取得失敗</span>';
      });
  });

  document.getElementById('selectAllCities').addEventListener('click', function () {
    cityBox.querySelectorAll('.city-check').forEach(cb => cb.checked = true);
    updateCityCount();
  });
  document.getElementById('clearCities').addEventListener('click', function () {
    cityBox.querySelectorAll('.city-check').forEach(cb => cb.checked = false);
    updateCityCount();
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

  document.getElementById('importForm').addEventListener('submit', function () {
    submitBtn.disabled = true;
    submitBtn.textContent = '取得中...';
  });

});
</script>
@endverbatim
@endpush
@endsection
