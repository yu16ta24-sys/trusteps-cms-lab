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
    if (!empty($sc['m_ind_name']))       $condParts[] = $sc['m_ind_name'];
    elseif (!empty($sc['big_ind_name'])) $condParts[] = $sc['big_ind_name'];
    if (!empty($sc['city_codes']))       $condParts[] = count($sc['city_codes']) . '市区町村指定';
    $condParts[] = ($sc['limit'] ?? 50) . '件';
  @endphp

  <details class="form-section" style="margin-bottom:20px;">
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

        <div style="display:grid;grid-template-columns:120px auto;gap:16px;align-items:end;">
          <div class="field">
            <label>上限件数</label>
            <select name="limit">
              @foreach([10,25,50,75,100,150,200,300,500] as $n)
                <option value="{{ $n }}" {{ ($sc['limit'] ?? 50) == $n ? 'selected' : '' }}>{{ $n }}件</option>
              @endforeach
            </select>
          </div>

          <div style="padding-bottom:2px;">
            <button type="submit" class="button" id="reSubmitBtn" style="min-width:120px;">再取得</button>
          </div>
        </div>

        <input type="hidden" name="industry_type" value="pref">
        <input type="hidden" name="industry_id"   value="">
        <input type="hidden" name="big_ind_name"  value="">
        <input type="hidden" name="m_ind_name"    value="">
      </form>
    </div>
  </details>

  @if(empty($mainResults) && empty($excludedResults))
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
    <div class="badge blue" style="font-size:14px;padding:8px 14px;">{{ count($mainResults) }}件取得</div>
    <div id="hpFoundBadge" style="display:none;" class="badge green">HP取得済 <span id="hpFoundCount">0</span>件</div>
    <div style="flex:1;"></div>
    <button type="button" class="button secondary" id="fetchHpBtn" style="gap:8px;">
      <span id="fetchHpBtnText">HP URLを取得する</span>
      <span id="fetchHpProgress" style="display:none;font-size:12px;font-weight:600;opacity:0.8;"></span>
    </button>
    <button type="button" class="button small light" id="selectAll">全選択</button>
    <button type="button" class="button small light" id="selectNone">全解除</button>
    <button type="button" class="button small light" id="selectHpOnly">HP URLありのみ</button>
    <div style="width:1px;height:20px;background:var(--line);margin:0 2px;flex-shrink:0;"></div>
    <button type="button" class="button small light" id="excludeSelectAll" style="color:#ef4444;">除外：全選択</button>
    <button type="button" class="button small light" id="excludeSelectNone">除外：全解除</button>
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
          <th class="tight">BZ除外</th>
          <th class="tight" style="color:#ef4444;">除外☑</th>
        </tr>
      </thead>
      <tbody>
        @foreach($mainResults as $i => $row)
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
              <span style="font-size:12px;color:var(--muted);">-</span>
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
          <td class="tight">
            <button type="button" class="button small light exclude-btn"
              data-index="{{ $i }}"
              style="font-size:11px;padding:5px 10px;color:var(--danger);">除外</button>
          </td>
          <td class="tight">
            @if(!$row['is_duplicate'])
              <input type="checkbox" class="exclude-check"
                value="{{ $i }}"
                data-detail-url="{{ $row['detail_url'] }}"
                style="accent-color:#ef4444;width:15px;height:15px;cursor:pointer;">
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- 保存バー --}}
  <div class="form-section compact" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <span id="selectedCount" style="font-weight:800;font-size:14px;color:var(--muted);">0件選択中</span>
    <button type="button" class="button light" id="saveBtn" disabled style="min-width:200px;">
      source_recordsに保存
    </button>
    <button type="button" class="button" id="saveCompaniesBtn" disabled style="min-width:200px;">
      companiesに直接保存
    </button>
    <div id="saveResult"></div>
  </div>

  {{-- 一括実行バー --}}
  @php $totalNonDup = count(array_filter($mainResults, fn($r) => !$r['is_duplicate'])); @endphp
  <div class="form-section compact" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;border-top:1px solid var(--line);padding-top:14px;">
    <span style="font-size:12px;color:var(--muted);font-weight:700;flex-shrink:0;">一括実行：</span>
    <span id="excludeSummary" style="font-weight:800;font-size:14px;">除外 0件 / カンパニー化 {{ $totalNonDup }}件</span>
    <button type="button" class="button" id="execBtn">実行</button>
    <div id="execResult"></div>
  </div>

  {{-- 除外済みsource_record アコーディオン --}}
  @if(!empty($excludedSourceResults))
  <details style="margin-top:16px;" id="excludedSourcePanel">
    <summary style="cursor:pointer;list-style:none;display:flex;align-items:center;gap:10px;padding:12px 16px;border:1px solid var(--line);border-radius:10px;background:#f8fafc;">
      <span style="font-weight:900;font-size:14px;">除外済み（source_record登録済み）</span>
      <span class="badge gray" style="font-size:11px;">{{ count($excludedSourceResults) }}件</span>
      <span style="font-size:12px;color:var(--muted);margin-left:4px;">▼ 以前の取得で除外済み — メインカウントから外しています</span>
    </summary>
    <div style="margin-top:8px;">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>会社名</th>
              <th>都道府県</th>
              <th>市区町村</th>
              <th>業種</th>
              <th class="tight">詳細</th>
            </tr>
          </thead>
          <tbody>
            @foreach($excludedSourceResults as $row)
            <tr>
              <td style="font-weight:800;opacity:0.6;">{{ $row['name'] ?? '-' }}</td>
              <td style="opacity:0.6;">{{ $row['pref'] ?? '-' }}</td>
              <td style="opacity:0.6;">{{ $row['city'] ?? '-' }}</td>
              <td style="opacity:0.6;"><span style="font-size:12px;color:var(--muted);">{{ Str::limit($row['industry'] ?? '-', 25) }}</span></td>
              <td class="tight">
                <a href="{{ $row['detail_url'] }}" target="_blank" class="button small light"
                  style="font-size:11px;padding:5px 10px;">BIZMAPS</a>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </details>
  @endif

  {{-- 除外リストアコーディオン --}}
  @if(!empty($excludedResults))
  <details style="margin-top:20px;" id="excludedPanel">
    <summary style="cursor:pointer;list-style:none;display:flex;align-items:center;gap:10px;padding:12px 16px;border:1px solid #fecaca;border-radius:14px;background:#fef2f2;">
      <span style="font-weight:900;font-size:14px;">除外リスト</span>
      <span class="badge red" style="font-size:11px;">{{ count($excludedResults) }}件</span>
      <span style="font-size:12px;color:var(--muted);margin-left:4px;">▼ クリックで展開 / 復活できます</span>
    </summary>
    <div style="margin-top:8px;">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>会社名</th>
              <th>都道府県</th>
              <th>市区町村</th>
              <th>業種</th>
              <th class="tight">詳細</th>
              <th class="tight">復活</th>
            </tr>
          </thead>
          <tbody id="excludedTbody">
            @foreach($excludedResults as $row)
            <tr id="excluded-row-{{ $loop->index }}">
              <td style="font-weight:800;opacity:0.6;">{{ $row['name'] ?? '-' }}</td>
              <td style="opacity:0.6;">{{ $row['pref'] ?? '-' }}</td>
              <td style="opacity:0.6;">{{ $row['city'] ?? '-' }}</td>
              <td style="opacity:0.6;"><span style="font-size:12px;color:var(--muted);">{{ Str::limit($row['industry'] ?? '-', 25) }}</span></td>
              <td class="tight">
                <a href="{{ $row['detail_url'] }}" target="_blank" class="button small light"
                  style="font-size:11px;padding:5px 10px;">BIZMAPS</a>
              </td>
              <td class="tight">
                <button type="button" class="button small light unexclude-btn"
                  data-detail-url="{{ $row['detail_url'] }}"
                  data-row-id="excluded-row-{{ $loop->index }}"
                  style="font-size:11px;padding:5px 10px;color:var(--success);">復活</button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </details>
  @endif

  @endif

</div>
@endsection

{{-- Blade変数をJSに渡す（verbatim外） --}}
@push('scripts')
<script>
const PREVIEW_DATA     = @json($mainResults);
const SAVED_CITY_CODES = @json($sc['city_codes'] ?? []);
const SAVED_PREF_ID    = @json($sc['prefecture_id'] ?? null);
</script>
@endpush

@push('scripts')
@verbatim
<script>
document.addEventListener('DOMContentLoaded', function () {

  // ---- 再検索パネル ----
  const rePrefSelect = document.getElementById('rePrefSelect');
  const reCityBox    = document.getElementById('reCityCheckboxes');

  function loadReCities(prefId, selectedCodes) {
    if (!prefId) {
      reCityBox.innerHTML = '<span style="color:var(--muted);font-size:13px;">都道府県を選択してください</span>';
      return;
    }
    reCityBox.innerHTML = '<span style="color:var(--muted);font-size:13px;">読み込み中...</span>';
    fetch(`/bizmaps/municipalities?prefecture_id=${prefId}`)
      .then(r => r.json())
      .then(cities => {
        reCityBox.innerHTML = cities.map(c => `
          <label style="display:inline-flex;align-items:center;gap:5px;margin:3px 8px 3px 0;font-size:13px;cursor:pointer;font-weight:600;">
            <input class="re-city-check" type="checkbox" name="city_codes[]" value="${c.code}"
              ${(window.SAVED_CITY_CODES || []).map(String).includes(String(c.code)) ? 'checked' : ''}
              style="accent-color:var(--primary);width:14px;height:14px;">
            ${c.name}
          </label>
        `).join('');
      });
  }

  if (rePrefSelect) {
    loadReCities(rePrefSelect.value, window.SAVED_CITY_CODES || []);
    rePrefSelect.addEventListener('change', function () {
      loadReCities(this.value, []);
    });
  }

  document.getElementById('reSelectAllCities')?.addEventListener('click', () => {
    reCityBox.querySelectorAll('.re-city-check').forEach(cb => cb.checked = true);
  });
  document.getElementById('reClearCities')?.addEventListener('click', () => {
    reCityBox.querySelectorAll('.re-city-check').forEach(cb => cb.checked = false);
  });

  document.getElementById('reSearchForm')?.addEventListener('submit', function () {
    const btn = document.getElementById('reSubmitBtn');
    if (btn) { btn.disabled = true; btn.textContent = '取得中...'; }
  });

  // ---- チェックボックス ----
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
      cb.checked = !!(PREVIEW_DATA[parseInt(cb.value)]?.hp_url);
    });
    updateCount();
  });

  document.querySelectorAll('.row-check').forEach(cb => cb.addEventListener('change', updateCount));
  updateCount();

  function updateCount() {
    const count = document.querySelectorAll('.row-check:checked').length;
    const el = document.getElementById('selectedCount');
    if (el) el.textContent = count + '件選択中';
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) saveBtn.disabled = count === 0;
    const saveCompaniesBtn = document.getElementById('saveCompaniesBtn');
    if (saveCompaniesBtn) saveCompaniesBtn.disabled = count === 0;
  }

  // ---- 除外ボタン ----
  document.querySelectorAll('.exclude-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const idx  = parseInt(this.dataset.index);
      const item = PREVIEW_DATA[idx];
      if (!item) return;

      if (!confirm(`「${item.name}」を除外リストに追加しますか？`)) return;

      fetch('/bizmaps/exclude', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ item }),
      })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          const row = document.getElementById('row-' + idx);
          if (row) {
            row.style.transition = 'opacity 0.3s';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
          }
          // 除外パネルがあれば件数バッジを更新
          const panel = document.getElementById('excludedPanel');
          if (!panel) {
            // パネルがない場合はページリロードで反映
          }
        }
      })
      .catch(err => alert('除外失敗: ' + err.message));
    });
  });

  // ---- 復活ボタン ----
  document.querySelectorAll('.unexclude-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const detailUrl = this.dataset.detailUrl;
      const rowId     = this.dataset.rowId;

      fetch('/bizmaps/unexclude', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ detail_url: detailUrl }),
      })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          const row = document.getElementById(rowId);
          if (row) {
            row.style.transition = 'opacity 0.3s';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
          }
        }
      })
      .catch(err => alert('復活失敗: ' + err.message));
    });
  });

  // ---- SSE: HP URL リアルタイム取得 ----
  let hpFoundCount = 0;
  let sseActive    = false;
  const totalRows  = PREVIEW_DATA.length;

  const fetchHpBtn      = document.getElementById('fetchHpBtn');
  const fetchHpBtnText  = document.getElementById('fetchHpBtnText');
  const fetchHpProgress = document.getElementById('fetchHpProgress');
  const hpFoundBadge    = document.getElementById('hpFoundBadge');
  const hpFoundCountEl  = document.getElementById('hpFoundCount');

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

          const hpCell = document.getElementById('hp-cell-' + idx);
          if (hpCell) {
            hpCell.innerHTML = `<a href="${data.hp_url}" target="_blank"
              style="font-size:12px;color:var(--primary);word-break:break-all;text-decoration:none;font-weight:700;">
              ${data.hp_url.length > 35 ? data.hp_url.substring(0, 35) + '…' : data.hp_url}
            </a>`;
          }

          const statusCell = document.getElementById('status-cell-' + idx);
          if (statusCell) statusCell.innerHTML = '<span class="badge green">HP✓</span>';

          if (PREVIEW_DATA[idx]) PREVIEW_DATA[idx].hp_url = data.hp_url;

          const cb = document.querySelector(`.row-check[value="${idx}"]`);
          if (cb && !PREVIEW_DATA[idx]?.is_duplicate) cb.checked = true;
          updateCount();
        }

        if (data.industry && PREVIEW_DATA[idx]) {
          PREVIEW_DATA[idx].industry = data.industry;
          const indCell = document.getElementById('industry-cell-' + idx);
          if (indCell) {
            const t = data.industry;
            indCell.innerHTML = `<span style="font-size:12px;color:var(--muted);">${t.length > 25 ? t.substring(0,25) + '…' : t}</span>`;
          }
        }
      };

      es.addEventListener('done', function () {
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
  const saveBtn          = document.getElementById('saveBtn');
  const saveCompaniesBtn = document.getElementById('saveCompaniesBtn');
  const saveResult       = document.getElementById('saveResult');

  function doSave(url, btnEl, labelText) {
    const checked = document.querySelectorAll('.row-check:checked');
    if (checked.length === 0) return;

    const items = Array.from(checked).map(cb => PREVIEW_DATA[parseInt(cb.value)]);

    btnEl.disabled = true;
    btnEl.textContent = '保存中...';
    saveResult.innerHTML = '';

    fetch(url, {
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
        `<span class="badge green" style="font-size:13px;padding:8px 14px;">${labelText} ${data.saved}件</span>` +
        (data.skipped > 0 ? ` <span class="badge gray" style="font-size:12px;">スキップ ${data.skipped}件</span>` : '');

      checked.forEach(cb => {
        const row = document.getElementById('row-' + cb.value);
        if (row) row.style.opacity = '0.45';
        const span = document.createElement('span');
        span.className = 'badge gray';
        span.style.fontSize = '11px';
        span.textContent = '保存済';
        cb.replaceWith(span);
      });

      btnEl.disabled = false;
      btnEl.textContent = labelText.replace('保存完了', '').trim() + 'に保存';
      updateCount();
    })
    .catch(err => {
      saveResult.innerHTML = `<span class="badge red">保存失敗: ${err.message}</span>`;
      btnEl.disabled = false;
      btnEl.textContent = labelText.replace('保存完了', '').trim() + 'に保存';
    });
  }

  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      doSave('/bizmaps/store', saveBtn, 'source_records保存完了');
    });
  }

  if (saveCompaniesBtn) {
    saveCompaniesBtn.addEventListener('click', function () {
      doSave('/bizmaps/store-companies', saveCompaniesBtn, 'companies保存完了');
    });
  }

  // ---- 除外チェックボックス（一括実行用） ----
  function updateExcludeCount() {
    const all      = document.querySelectorAll('.exclude-check');
    const excluded = document.querySelectorAll('.exclude-check:checked').length;
    const company  = all.length - excluded;
    const el = document.getElementById('excludeSummary');
    if (el) el.textContent = `除外 ${excluded}件 / カンパニー化 ${company}件`;
  }

  document.querySelectorAll('.exclude-check').forEach(cb => {
    cb.addEventListener('change', updateExcludeCount);
  });
  updateExcludeCount();

  document.getElementById('excludeSelectAll')?.addEventListener('click', () => {
    document.querySelectorAll('.exclude-check').forEach(cb => cb.checked = true);
    updateExcludeCount();
  });
  document.getElementById('excludeSelectNone')?.addEventListener('click', () => {
    document.querySelectorAll('.exclude-check').forEach(cb => cb.checked = false);
    updateExcludeCount();
  });

  // ---- 一括実行ボタン ----
  document.getElementById('execBtn')?.addEventListener('click', function () {
    const excludedUrls = Array.from(document.querySelectorAll('.exclude-check:checked'))
      .map(cb => cb.dataset.detailUrl)
      .filter(Boolean);
    const total   = document.querySelectorAll('.exclude-check').length;
    const company = total - excludedUrls.length;

    if (!confirm(`除外 ${excludedUrls.length}件 / カンパニー化 ${company}件 で実行しますか？`)) return;

    this.disabled = true;
    this.textContent = '処理中...';
    const execResult = document.getElementById('execResult');
    if (execResult) execResult.innerHTML = '';

    fetch('/bizmaps/store-with-exclusion', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({
        items: PREVIEW_DATA,
        excluded_detail_urls: excludedUrls,
      }),
    })
    .then(r => r.json())
    .then(data => {
      if (execResult) {
        execResult.innerHTML =
          `<span class="badge green" style="font-size:13px;padding:6px 12px;">カンパニー化 ${data.saved_companies}件</span> ` +
          `<span class="badge gray" style="font-size:12px;">除外登録 ${data.saved_excluded}件</span>` +
          (data.skipped > 0 ? ` <span class="badge amber" style="font-size:12px;">スキップ ${data.skipped}件</span>` : '');
      }
      this.textContent = '実行完了';
    })
    .catch(err => {
      if (execResult) execResult.innerHTML = `<span class="badge red">エラー: ${err.message}</span>`;
      this.disabled = false;
      this.textContent = '実行';
    });
  });

});
</script>
@endverbatim
@endpush
