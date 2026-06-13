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

  {{-- プレビュー取得モーダル --}}
  <div id="previewModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:18px;padding:32px;width:min(480px,90vw);box-shadow:0 20px 60px rgba(0,0,0,.25);">
      <h3 style="margin:0 0 20px;font-size:16px;font-weight:900;color:var(--text);">企業リスト取得中</h3>
      <div style="background:#e2e6ed;border-radius:4px;height:8px;margin-bottom:14px;overflow:hidden;">
        <div id="previewModalBar" style="background:var(--primary,#3b82f6);height:100%;border-radius:4px;width:0%;transition:width .4s ease;"></div>
      </div>
      <p id="previewModalStatus" style="font-size:13px;font-weight:700;color:var(--text);margin:0 0 22px;">接続中...</p>
      <button id="previewModalClose" class="button light" style="display:none;">閉じる</button>
    </div>
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
                <option value="{{ $pref->id }}" {{ ($searchCondition['prefecture_id'] ?? '') == $pref->id ? 'selected' : '' }}>{{ $pref->name }}</option>
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
              @foreach([10,25,50,75,100,150,200,300,500] as $n)
                <option value="{{ $n }}" {{ ($searchCondition['limit'] ?? 50) == $n ? 'selected' : '' }}>{{ $n }}件</option>
              @endforeach
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
<script>
const IMPORT_SAVED_SC = @json($searchCondition);
</script>
@verbatim
<script>
document.addEventListener('DOMContentLoaded', function () {

  const prefSelect  = document.getElementById('prefectureSelect');
  const cityBox     = document.getElementById('cityCheckboxes');
  const cityCount   = document.getElementById('citySelectedCount');
  const submitBtn   = document.getElementById('submitBtn');

  function loadCities(prefId, savedCodes) {
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
        const savedSet = (savedCodes || []).map(String);
        cityBox.innerHTML = cities.map(c => `
          <label style="display:inline-flex;align-items:center;gap:5px;margin:3px 8px 3px 0;font-size:13px;cursor:pointer;font-weight:600;">
            <input class="city-check" type="checkbox" name="city_codes[]" value="${c.code}"
              ${savedSet.includes(String(c.code)) ? 'checked' : ''}
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
  }

  prefSelect.addEventListener('change', function () {
    loadCities(this.value, []);
  });

  // 前回の検索条件を復元
  if (window.IMPORT_SAVED_SC && IMPORT_SAVED_SC.prefecture_id) {
    prefSelect.value = IMPORT_SAVED_SC.prefecture_id;
    loadCities(IMPORT_SAVED_SC.prefecture_id, IMPORT_SAVED_SC.city_codes || []);
  }

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

  const previewModal       = document.getElementById('previewModal');
  const previewModalBar    = document.getElementById('previewModalBar');
  const previewModalStatus = document.getElementById('previewModalStatus');
  const previewModalClose  = document.getElementById('previewModalClose');

  document.getElementById('importForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    const params   = new URLSearchParams();
    for (const [key, value] of formData.entries()) {
      if (key === '_token') continue;
      params.append(key, value);
    }

    previewModal.style.display      = 'flex';
    previewModalBar.style.width     = '0%';
    previewModalStatus.textContent  = '接続中...';
    previewModalClose.style.display = 'none';
    submitBtn.disabled = true;
    submitBtn.textContent = '取得中...';

    const es = new EventSource('/bizmaps/preview-stream?' + params.toString());

    es.onmessage = function (ev) {
      const data = JSON.parse(ev.data);

      if (data.finished) {
        es.close();
        previewModalBar.style.width    = '100%';
        previewModalStatus.textContent = data.main_count + '件取得完了';
        previewModalClose.style.display = 'inline-block';
        previewModalClose.textContent   = '結果を確認する';
        previewModalClose.onclick       = () => { window.location.href = '/bizmaps/preview-result'; };
        return;
      }

      const pct = data.total > 0 ? Math.round((data.done / data.total) * 100) : 0;
      previewModalBar.style.width    = pct + '%';
      previewModalStatus.textContent = '取得中: ' + data.done + '/' + data.total + '件';
    };

    es.onerror = function () {
      es.close();
      previewModalStatus.textContent  = 'エラーが発生しました。もう一度お試しください。';
      previewModalClose.style.display = 'inline-block';
      previewModalClose.textContent   = '閉じる';
      previewModalClose.onclick       = function () {
        previewModal.style.display = 'none';
        submitBtn.disabled         = false;
        submitBtn.textContent      = 'プレビュー取得';
      };
    };
  });

});
</script>
@endverbatim
@endpush
@endsection
