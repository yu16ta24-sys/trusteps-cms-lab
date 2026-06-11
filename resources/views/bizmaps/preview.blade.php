@extends('layouts.app')

@section('title', 'BIZMAPSインポート - プレビュー')

@section('content')
<div class="content">

  {{-- ヘッダー --}}
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
      <p class="page-kicker">データ収集 / プレビュー</p>
      <h1 class="page-title" style="font-size:clamp(24px,3vw,36px);">取得結果</h1>
    </div>
    <a href="{{ route('bizmaps.import') }}" class="button light small" style="align-self:center;">
      ← 条件に戻る
    </a>
  </div>

  {{-- 折りたたみ検索条件パネル --}}
  @php
    $sc = $searchCondition ?? session('bizmaps_search_condition', []);
    $condParts = [];
    if (!empty($sc['prefecture_name'])) $condParts[] = $sc['prefecture_name'];
    if (!empty($sc['m_ind_name']))      $condParts[] = $sc['m_ind_name'];
    elseif (!empty($sc['big_ind_name'])) $condParts[] = $sc['big_ind_name'];
    if (!empty($sc['city_codes']))      $condParts[] = count($sc['city_codes']) . '市区町村指定';
    $condParts[] = ($sc['limit'] ?? 50) . '件';
  @endphp

  <details class="form-section" style="margin-bottom:20px;" id="conditionPanel">
    <summary style="cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:4px 0;">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <span style="font-weight:900;font-size:15px;">検索条件</span>
        <span style="font-size:13px;color:var(--muted);">{{ implode(' / ', $condParts) }}</span>
      </div>
      <span class="badge gray" style="font-size:11px;flex-shrink:0;">▼ 変更・再取得</span>
    </summary>

    <div style="margin-top:16px;">
      <form method="POST" action="{{ route('bizmaps.preview') }}" id="reSearchForm">
        @csrf

        <div style="display:grid;grid-template-columns:180px 1fr;gap:16px;align-items:start;margin-bottom:16px;">
          <div class="field required">
            <label>都道府県</label>
            <select name="prefecture_id" id="rePrefSelect" required>
              <option value="">選択してください</option>
              @foreach(\DB::table('prefectures')->orderBy('id')->get() as $pref)
                <option value="{{ $pref->id }}" {{ ($sc['prefecture_id'] ?? '') == $pref->id ? 'selected' : '' }}>
                  {{ $pref->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="field">
            <label>市区町村 <span style="font-weight:400;color:var(--muted);font-size:12px;">複数選択可</span></label>
            <div style="display:flex;gap:8px;margin-bottom:8px;">
              <button type="button" class="button small light" id="reSelectAllCities">全選択</button>
              <button type="button" class="button small light" id="reClearCities">クリア</button>
            </div>
            <div id="reCityCheckboxes" style="border:1px solid #d9e2ee;border-radius:14px;padding:12px;max-height:150px;overflow-y:auto;min-height:44px;background:rgba(255,255,255,.9);">
              <span style="color:var(--muted);font-size:13px;">読み込み中...</span>
            </div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 120px auto;gap:16px;align-items:end;">
          <div class="field">
            <label>大業種</label>
            <select name="big_ind_id" id="reBigIndSelect">
              <option value="">全業種</option>
              @foreach($industries as $ind)
                <option value="{{ $ind['big_id'] }}"
                  data-name="{{ $ind['big_name'] }}"
                  {{ ($sc['industry_type'] ?? '') === 'big_ind' && ($sc['industry_id'] ?? '') == $ind['big_id'] ? 'selected' : '' }}>
                  {{ $ind['big_name'] }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="field">
            <label>中業種</label>
            <select name="m_ind_id" id="reMIndSelect">
              <option value="">大業種を先に選択</option>
            </select>
          </div>

          <div class="field">
            <label>上限件数</label>
            <input type="number" name="limit" value="{{ $sc['limit'] ?? 50 }}" min="1" max="500">
          </div>

          <div style="padding-bottom:2px;">
            <button type="submit" class="button" style="min-width:120px;">再取得</button>
          </div>
        </div>

        <input type="hidden" name="industry_type" id="reIndustryTypeInput" value="{{ $sc['industry_type'] ?? 'pref' }}">
        <input type="hidden" name="industry_id"   id="reIndustryIdInput"   value="{{ $sc['industry_id'] ?? '' }}">
        <input type="hidden" name="big_ind_name"  id="reBigIndNameInput"   value="{{ $sc['big_ind_name'] ?? '' }}">
        <input type="hidden" name="m_ind_name"    id="reMIndNameInput"     value="{{ $sc['m_ind_name'] ?? '' }}">
      </form>
    </div>
  </details>

  @if(empty($results))
    <div class="card" style="text-align:center;padding:48px;">
      <div class="empty-icon" style="margin:0 auto 16px;">0</div>
      <h2 class="empty-title">取得できませんでした</h2>
      <p class="empty-copy">条件を変えて再度お試しください。</p>
      <div style="margin-top:20px;">
        <a href="{{ route('bizmaps.import') }}" class="button">条件に戻る</a>
      </div>
    </div>
  @else

  {{-- サマリーバー --}}
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <div class="badge blue" style="font-size:14px;padding:8px 14px;">{{ count($results) }}件取得</div>
    <div id="hpFoundBadge" style="display:none;" class="badge green">HP取得済 <span id="hpFoundCount">0</span>件</div>
    <div style="flex:1;"></div>

    {{-- HP URL取得ボタン --}}
    <button type="button" class="button secondary" id="fetchHpBtn" style="gap:8px;">
      <span id="fetchHpBtnText">HP URLを取得する</span>
      <span id="fetchHpProgress" style="display:none;font-size:12px;font-weight:600;opacity:0.8;"></span>
    </button>

    <button type="button" class="button small light" id="selectAll">全選択</button>
    <button type="button" class="button small light" id="selectNone">全解除</button>
    <button type="button" class="button small light" id="selectHpOnly">HP URLありのみ</button>
  </div>

  {{-- テーブル --}}
  <div class="table-wrap" style="margin-bottom:20px;">
    <table>
      <thead>
        <tr>
          <th class="tight">
            <input type="checkbox" id="checkAll" style="accent-color:var(--primary);width:15px;height:15px;">
          </th>
          <th>会社名</th>
          <th>都道府県</th>
          <th>市区町村</th>
          <th>業種</th>
          <th>HP URL</th>
          <th class="tight">詳細</th>
          <th class="tight">状態</th>
        </tr>
      </thead>
      <tbody>
        @foreach($results as $i => $row)
        <tr id="row-{{ $i }}">
          <td class="tight">
            @if($row['is_duplicate'])
              <span class="badge gray" style="font-size:11px;">保存済</span>
            @else
              <input type="checkbox" class="row-check" value="{{ $i }}"
                style="accent-color:var(--primary);width:15px;height:15px;cursor:pointer;">
            @endif
          </td>
          <td style="font-weight:800;max-width:260px;">{{ $row['name'] ?? '-' }}</td>
          <td style="white-space:nowrap;">{{ $row['pref'] ?? '-' }}</td>
          <td style="white-space:nowrap;">{{ $row['city'] ?? '-' }}</td>
          <td style="max-width:140px;" id="industry-cell-{{ $i }}">
            <span style="font-size:12px;color:var(--muted);">{{ Str::limit($row['industry'] ?? '-', 25) }}</span>
          </td>
          <td style="max-width:220px;" id="hp-cell-{{ $i }}">
            @if(!empty($row['hp_url']))
              <a href="{{ $row['hp_url'] }}" target="_blank"
                style="font-size:12px;color:var(--primary);word-break:break-all;text-decoration:none;font-weight:700;">
                {{ Str::limit($row['hp_url'], 35) }}
              </a>
            @else
              <span style="font-size:12px;color:var(--muted);" class="hp-placeholder">-</span>
            @endif
          </td>
          <td class="tight">
            <a href="{{ $row['detail_url'] }}" target="_blank" class="button small light"
              style="font-size:11px;padding:5px 10px;">BIZMAPS</a>
          </td>
          <td class="tight" id="status-cell-{{ $i }}">
            @if($row['is_duplicate'])
              <span class="badge gray">重複</span>
            @elseif(!empty($row['hp_url']))
              <span class="badge green">HP✓</span>
            @else
              <span class="badge amber">HPなし</span>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- 保存バー --}}
  <div class="form-section compact" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
    <span id="selectedCount" style="font-weight:800;font-size:14px;color:var(--muted);">0件選択中</span>
    <button type="button" class="button" id="saveBtn" disabled style="min-width:220px;">
      選択した企業をsource_recordsに保存
    </button>
    <div id="saveResult"></div>
  </div>

  @endif

</div>

<script>
const PREVIEW_DATA = @json($results);
</script>

@push('scripts')
<script>
@verbatim
document.addEventListener('DOMContentLoaded', function () {

  // チェックボックス全選択
  const checkAll = document.getElementById('checkAll');
  if (checkAll) {
    checkAll.addEventListener('change', function () {
      document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
      updateCount();
    });
  }

  document.getElementById('selectAll')?.addEventListener('click', () => {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = true); updateCount();
  });
  document.getElementById('selectNone')?.addEventListener('click', () => {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false); updateCount();
  });
  document.getElementById('selectHpOnly')?.addEventListener('click', () => {
    document.querySelectorAll('.row-check').forEach(cb => {
      const idx = parseInt(cb.value);
      cb.checked = !!(PREVIEW_DATA[idx]?.hp_url);
    });
    updateCount();
  });

  document.querySelectorAll('.row-check').forEach(cb => cb.addEventListener('change', updateCount));
  updateCount();

  function updateCount() {
    const count = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('selectedCount').textContent = count + '件選択中';
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) saveBtn.disabled = count === 0;
  }

  // ---- 再検索パネル ----
  const rePrefSelect   = document.getElementById('rePrefSelect');
  const reCityBox      = document.getElementById('reCityCheckboxes');
  const reBigIndSelect = document.getElementById('reBigIndSelect');
  const reMIndSelect   = document.getElementById('reMIndSelect');
  const reIndTypeInput = document.getElementById('reIndustryTypeInput');
  const reIndIdInput   = document.getElementById('reIndustryIdInput');
  const reBigIndName   = document.getElementById('reBigIndNameInput');
  const reMIndName     = document.getElementById('reMIndNameInput');

  const savedCityCodes = @json($sc['city_codes'] ?? []);
  const savedMIndId    = @json($sc['industry_type'] === 'm_ind' ? ($sc['industry_id'] ?? null) : null);

  function loadReCities(prefId, selectedCodes) {
    if (!prefId) { reCityBox.innerHTML = '<span style="color:var(--muted);font-size:13px;">都道府県を選択してください</span>'; return; }
    reCityBox.innerHTML = '<span style="color:var(--muted);font-size:13px;">読み込み中...</span>';
    fetch(`/bizmaps/municipalities?prefecture_id=${prefId}`)
      .then(r => r.json())
      .then(cities => {
        reCityBox.innerHTML = cities.map(c => `
          <label style="display:inline-flex;align-items:center;gap:5px;margin:3px 8px 3px 0;font-size:13px;cursor:pointer;font-weight:600;">
            <input class="re-city-check" type="checkbox" name="city_codes[]" value="${c.code}"
              ${selectedCodes.includes(c.code) || selectedCodes.includes(String(c.code)) ? 'checked' : ''}
              style="accent-color:var(--primary);width:14px;height:14px;">
            ${c.name}
          </label>
        `).join('');
        updateReIndustryType();
        reCityBox.querySelectorAll('.re-city-check').forEach(cb => cb.addEventListener('change', updateReIndustryType));
      });
  }

  if (rePrefSelect) {
    loadReCities(rePrefSelect.value, savedCityCodes);
    rePrefSelect.addEventListener('change', function () {
      loadReCities(this.value, []);
    });
  }

  document.getElementById('reSelectAllCities')?.addEventListener('click', () => {
    reCityBox.querySelectorAll('.re-city-check').forEach(cb => cb.checked = true); updateReIndustryType();
  });
  document.getElementById('reClearCities')?.addEventListener('click', () => {
    reCityBox.querySelectorAll('.re-city-check').forEach(cb => cb.checked = false); updateReIndustryType();
  });

  if (reBigIndSelect) {
    reBigIndSelect.addEventListener('change', function () {
      const bigId = this.value;
      reMIndSelect.innerHTML = '<option value="">全て</option>';
      if (!bigId) { updateReIndustryType(); return; }
      fetch(`/bizmaps/sub-industries?big_ind_id=${bigId}`)
        .then(r => r.json())
        .then(subs => {
          subs.forEach(s => {
            reMIndSelect.innerHTML += `<option value="${s.id}" ${savedMIndId == s.id ? 'selected' : ''}>${s.name}</option>`;
          });
          updateReIndustryType();
        });
    });
    // 初期ロード時に中業種を復元
    if (reBigIndSelect.value) {
      reBigIndSelect.dispatchEvent(new Event('change'));
    }
  }

  reMIndSelect?.addEventListener('change', updateReIndustryType);

  function updateReIndustryType() {
    const prefId  = rePrefSelect?.value;
    const checked = reCityBox.querySelectorAll('.re-city-check:checked').length;
    const bigId   = reBigIndSelect?.value;
    const mIndId  = reMIndSelect?.value;
    const bigOpt  = reBigIndSelect?.selectedOptions[0];
    const mOpt    = reMIndSelect?.selectedOptions[0];

    if (mIndId) {
      reIndTypeInput.value = 'm_ind'; reIndIdInput.value = mIndId;
      reMIndName.value = mOpt?.text || '';
      reBigIndName.value = bigOpt?.text || '';
    } else if (bigId) {
      reIndTypeInput.value = 'big_ind'; reIndIdInput.value = bigId;
      reBigIndName.value = bigOpt?.text || '';
      reMIndName.value = '';
    } else if (checked > 0) {
      reIndTypeInput.value = 'city'; reIndIdInput.value = '';
      reBigIndName.value = ''; reMIndName.value = '';
    } else if (prefId) {
      reIndTypeInput.value = 'pref'; reIndIdInput.value = '';
      reBigIndName.value = ''; reMIndName.value = '';
    }
  }

  // 再取得フォームのsubmit
  document.getElementById('reSearchForm')?.addEventListener('submit', function (e) {
    const btn = this.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = '取得中...'; }
  });
  let hpFoundCount = 0;
  let sseActive = false;

  const fetchHpBtn      = document.getElementById('fetchHpBtn');
  const fetchHpBtnText  = document.getElementById('fetchHpBtnText');
  const fetchHpProgress = document.getElementById('fetchHpProgress');
  const hpFoundBadge    = document.getElementById('hpFoundBadge');
  const hpFoundCountEl  = document.getElementById('hpFoundCount');
  const totalRows       = PREVIEW_DATA.length;

  if (fetchHpBtn) {
    fetchHpBtn.addEventListener('click', function () {
      if (sseActive) return;
      sseActive = true;
      fetchHpBtn.disabled = true;
      fetchHpBtnText.textContent = '取得中...';
      fetchHpProgress.style.display = 'inline';
      fetchHpProgress.textContent = '0 / ' + totalRows;

      let processed = 0;

      const es = new EventSource('/bizmaps/fetch-hp-stream');

      es.onmessage = function (e) {
        const data = JSON.parse(e.data);
        const idx  = data.index;
        processed++;

        fetchHpProgress.textContent = processed + ' / ' + totalRows;

        if (data.hp_url) {
          hpFoundCount++;
          hpFoundBadge.style.display = 'inline-flex';
          hpFoundCountEl.textContent = hpFoundCount;

          // HP URLセルを更新
          const hpCell = document.getElementById('hp-cell-' + idx);
          if (hpCell) {
            hpCell.innerHTML = `<a href="${data.hp_url}" target="_blank"
              style="font-size:12px;color:var(--primary);word-break:break-all;text-decoration:none;font-weight:700;">
              ${data.hp_url.length > 35 ? data.hp_url.substring(0, 35) + '…' : data.hp_url}
            </a>`;
          }

          // 状態バッジを更新
          const statusCell = document.getElementById('status-cell-' + idx);
          if (statusCell) {
            statusCell.innerHTML = '<span class="badge green">HP✓</span>';
          }

          // PREVIEW_DATAも更新（保存時に使う）
          if (PREVIEW_DATA[idx]) {
            PREVIEW_DATA[idx].hp_url = data.hp_url;
          }
        }

        // 業種セルを更新（HP取得あり/なし問わず）
        if (data.industry) {
          const industryCell = document.getElementById('industry-cell-' + idx);
          if (industryCell) {
            industryCell.innerHTML = `<span style="font-size:12px;color:var(--muted);">${data.industry.substring(0, 25)}${data.industry.length > 25 ? '…' : ''}</span>`;
          }
          if (PREVIEW_DATA[idx]) {
            PREVIEW_DATA[idx].industry = data.industry;
          }

          // チェックボックスをONに
          const cb = document.querySelector(`.row-check[value="${idx}"]`);
          if (cb && !PREVIEW_DATA[idx]?.is_duplicate) cb.checked = true;
          updateCount();
        }
      };

      es.addEventListener('done', function (e) {
        es.close();
        sseActive = false;
        fetchHpBtn.disabled = false;
        fetchHpBtnText.textContent = 'HP URL取得完了';
        fetchHpProgress.textContent = hpFoundCount + '件取得';
      });

      es.onerror = function () {
        es.close();
        sseActive = false;
        fetchHpBtn.disabled = false;
        fetchHpBtnText.textContent = 'HP URLを取得する（再試行）';
        fetchHpProgress.textContent = 'エラー';
      };
    });
  }

  // ---- 保存 ----
  const saveBtn    = document.getElementById('saveBtn');
  const saveResult = document.getElementById('saveResult');

  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      const checked = document.querySelectorAll('.row-check:checked');
      if (checked.length === 0) return;

      const items = Array.from(checked).map(cb => PREVIEW_DATA[parseInt(cb.value)]);

      saveBtn.disabled = true;
      saveBtn.textContent = '保存中...';
      saveResult.innerHTML = '';

      fetch('/bizmaps/store', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ items }),
      })
      .then(r => r.json())
      .then(data => {
        saveResult.innerHTML =
          `<span class="badge green" style="font-size:13px;padding:8px 14px;">保存完了 ${data.saved}件</span>` +
          (data.skipped > 0 ? ` <span class="badge gray" style="font-size:12px;">スキップ ${data.skipped}件</span>` : '');

        checked.forEach(cb => {
          const row = document.getElementById('row-' + cb.value);
          if (row) row.style.opacity = '0.45';
          cb.replaceWith(Object.assign(document.createElement('span'), {
            className: 'badge gray', style: 'font-size:11px;'
          })).textContent = '保存済';
        });

        saveBtn.disabled = false;
        saveBtn.textContent = '選択した企業をsource_recordsに保存';
        updateCount();
      })
      .catch(err => {
        saveResult.innerHTML = `<span class="badge red">保存失敗: ${err.message}</span>`;
        saveBtn.disabled = false;
        saveBtn.textContent = '選択した企業をsource_recordsに保存';
      });
    });
  }

});
@endverbatim
</script>
@endpush
@endsection
