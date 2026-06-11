@extends('layouts.app')

@section('title', 'BIZMAPSインポート')

@section('content')
<div class="container-fluid py-4">
  <h1 class="h4 mb-4">BIZMAPSインポート</h1>

  <form method="POST" action="{{ route('bizmaps.preview') }}" id="importForm">
    @csrf

    <div class="row g-4">

      {{-- エリア選択 --}}
      <div class="col-12">
        <div class="card">
          <div class="card-header fw-bold">① エリア選択</div>
          <div class="card-body">

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">都道府県 <span class="text-danger">*</span></label>
                <select name="prefecture_id" id="prefectureSelect" class="form-select" required>
                  <option value="">-- 選択してください --</option>
                  @foreach($prefectures as $pref)
                    <option value="{{ $pref->id }}">{{ $pref->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-8">
                <label class="form-label">市区町村（複数選択可）</label>
                <div class="d-flex gap-2 mb-2">
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllCities">全選択</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="clearCities">クリア</button>
                </div>
                <div id="cityCheckboxes" class="border rounded p-2" style="max-height:200px;overflow-y:auto;min-height:60px;">
                  <span class="text-muted small">都道府県を選択してください</span>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- 業種選択 --}}
      <div class="col-12">
        <div class="card">
          <div class="card-header fw-bold">② 業種選択</div>
          <div class="card-body">

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">大業種</label>
                <select name="big_ind_id" id="bigIndSelect" class="form-select">
                  <option value="">-- 全業種（絞り込まない）--</option>
                  @foreach($industries as $ind)
                    <option value="{{ $ind['big_id'] }}">{{ $ind['big_name'] }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">中業種（任意）</label>
                <select name="m_ind_id" id="mIndSelect" class="form-select">
                  <option value="">-- 大業種を先に選択 --</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">検索軸</label>
                <div id="industryTypeDisplay" class="form-control-plaintext text-muted small pt-2">
                  都道府県/市区町村で検索
                </div>
                <input type="hidden" name="industry_type" id="industryTypeInput" value="pref">
                <input type="hidden" name="industry_id"   id="industryIdInput"   value="">
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- 取得設定 --}}
      <div class="col-12">
        <div class="card">
          <div class="card-header fw-bold">③ 取得設定</div>
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <div class="col-md-3">
                <label class="form-label">取得上限件数</label>
                <input type="number" name="limit" class="form-control" value="50" min="1" max="500">
                <div class="form-text">最大500件。詳細ページ取得なしの場合は速い。</div>
              </div>
              <div class="col-md-3">
                <div class="form-check mt-4">
                  <input class="form-check-input" type="checkbox" name="fetch_hp" id="fetchHp" value="1">
                  <label class="form-check-label" for="fetchHp">
                    詳細ページからHP URLを取得する
                    <span class="badge bg-warning text-dark ms-1">低速</span>
                  </label>
                </div>
              </div>
              <div class="col-md-6 text-end">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                  プレビュー取得
                </button>
              </div>
            </div>
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
  const bigIndSelect = document.getElementById('bigIndSelect');
  const mIndSelect   = document.getElementById('mIndSelect');
  const indTypeInput = document.getElementById('industryTypeInput');
  const indIdInput   = document.getElementById('industryIdInput');
  const indTypeDisp  = document.getElementById('industryTypeDisplay');

  // 都道府県選択 → 市区町村取得
  prefSelect.addEventListener('change', function () {
    const prefId = this.value;
    cityBox.innerHTML = '<span class="text-muted small">読み込み中...</span>';

    if (!prefId) {
      cityBox.innerHTML = '<span class="text-muted small">都道府県を選択してください</span>';
      updateIndustryType();
      return;
    }

    fetch(`/bizmaps/municipalities?prefecture_id=${prefId}`)
      .then(r => r.json())
      .then(cities => {
        if (cities.length === 0) {
          cityBox.innerHTML = '<span class="text-muted small">市区町村データがありません</span>';
          return;
        }
        cityBox.innerHTML = cities.map(c => `
          <div class="form-check form-check-inline me-2 mb-1">
            <input class="form-check-input city-check" type="checkbox"
                   name="city_codes[]" value="${c.code}" id="city_${c.code}">
            <label class="form-check-label small" for="city_${c.code}">${c.name}</label>
          </div>
        `).join('');
        updateIndustryType();
      })
      .catch(() => {
        cityBox.innerHTML = '<span class="text-danger small">取得失敗</span>';
      });
  });

  // 全選択・クリア
  document.getElementById('selectAllCities').addEventListener('click', function () {
    document.querySelectorAll('.city-check').forEach(cb => cb.checked = true);
    updateIndustryType();
  });
  document.getElementById('clearCities').addEventListener('click', function () {
    document.querySelectorAll('.city-check').forEach(cb => cb.checked = false);
    updateIndustryType();
  });

  // 市区町村チェック変化
  cityBox.addEventListener('change', updateIndustryType);

  // 大業種選択 → 中業種取得
  bigIndSelect.addEventListener('change', function () {
    const bigId = this.value;
    mIndSelect.innerHTML = '<option value="">-- 読み込み中 --</option>';

    if (!bigId) {
      mIndSelect.innerHTML = '<option value="">-- 大業種を先に選択 --</option>';
      updateIndustryType();
      return;
    }

    fetch(`/bizmaps/sub-industries?big_ind_id=${bigId}`)
      .then(r => r.json())
      .then(subs => {
        mIndSelect.innerHTML = '<option value="">-- 全て（大業種のみ）--</option>';
        subs.forEach(s => {
          mIndSelect.innerHTML += `<option value="${s.id}">${s.name}</option>`;
        });
        updateIndustryType();
      });
  });

  mIndSelect.addEventListener('change', updateIndustryType);

  function updateIndustryType() {
    const prefId   = prefSelect.value;
    const checkedCities = document.querySelectorAll('.city-check:checked');
    const bigId    = bigIndSelect.value;
    const mIndId   = mIndSelect.value;

    if (mIndId) {
      indTypeInput.value = 'm_ind';
      indIdInput.value   = mIndId;
      indTypeDisp.textContent = '中業種で絞り込み';
    } else if (bigId) {
      indTypeInput.value = 'big_ind';
      indIdInput.value   = bigId;
      indTypeDisp.textContent = '大業種で絞り込み';
    } else if (checkedCities.length > 0) {
      indTypeInput.value = 'city';
      indIdInput.value   = '';
      indTypeDisp.textContent = `市区町村 ${checkedCities.length}件で検索`;
    } else if (prefId) {
      indTypeInput.value = 'pref';
      indIdInput.value   = '';
      indTypeDisp.textContent = '都道府県全体で検索';
    } else {
      indTypeInput.value = 'pref';
      indIdInput.value   = '';
      indTypeDisp.textContent = '都道府県/市区町村で検索';
    }
  }
});
@endverbatim
</script>
@endpush
@endsection
