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
            <button type="button" class="button" id="reSubmitBtn" style="min-width:120px;">再取得</button>
          </div>
        </div>

        <input type="hidden" name="industry_type" value="pref">
        <input type="hidden" name="industry_id"   value="">
        <input type="hidden" name="big_ind_name"  value="">
        <input type="hidden" name="m_ind_name"    value="">
      </form>

      {{-- 再取得 SSEモーダル --}}
      <div id="rePreviewModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:18px;padding:32px;width:min(480px,90vw);box-shadow:0 20px 60px rgba(0,0,0,.25);">
          <h3 style="margin:0 0 20px;font-size:16px;font-weight:900;color:var(--text);">企業リスト再取得中</h3>
          <div style="background:#e2e6ed;border-radius:4px;height:8px;margin-bottom:14px;overflow:hidden;">
            <div id="rePreviewBar" style="background:var(--primary,#3b82f6);height:100%;border-radius:4px;width:0%;transition:width .4s ease;"></div>
          </div>
          <p id="rePreviewStatus" style="font-size:13px;font-weight:700;color:var(--text);margin:0 0 22px;">接続中...</p>
          <button id="rePreviewClose" class="button light" style="display:none;">閉じる</button>
        </div>
      </div>
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

  {{-- HP URL 取得モーダル --}}
  <div id="hpFetchModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:18px;padding:32px;width:min(480px,90vw);box-shadow:0 20px 60px rgba(0,0,0,.25);">
      <h3 style="margin:0 0 20px;font-size:16px;font-weight:900;color:var(--text);">HP URL 取得中</h3>
      <div style="background:#e2e6ed;border-radius:4px;height:8px;margin-bottom:14px;overflow:hidden;">
        <div id="hpModalBar" style="background:var(--primary,#3b82f6);height:100%;border-radius:4px;width:0%;transition:width .4s ease;"></div>
      </div>
      <p id="hpModalStatus" style="font-size:13px;font-weight:700;color:var(--text);margin:0 0 6px;">準備中...</p>
      <p id="hpModalDetail" style="font-size:12px;color:var(--muted);margin:0 0 22px;min-height:1.5em;"></p>
      <button id="hpModalClose" class="button light" style="display:none;">閉じる</button>
    </div>
  </div>

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
  </div>

  {{-- テーブル --}}
  <div class="table-wrap" style="margin-bottom:20px;">
    <table>
      <thead>
        <tr>
          <th class="tight">
            <input type="checkbox" id="checkAll" checked style="accent-color:var(--primary);width:15px;height:15px;">
          </th>
          <th>会社名</th>
          <th>都道府県</th>
          <th>市区町村</th>
          <th>業種</th>
          <th>HP URL</th>
          <th class="tight">詳細</th>
          <th class="tight">状態</th>
          <th class="tight">除外</th>
        </tr>
      </thead>
      <tbody>
        @foreach($mainResults as $i => $row)
        <tr id="row-{{ $i }}">
          <td class="tight">
            @if($row['is_duplicate'])
              <span class="badge gray" style="font-size:11px;">保存済</span>
            @else
              <input type="checkbox" class="row-check" value="{{ $i }}" checked
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
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- アクションバー --}}
  <div class="form-section compact" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <span id="selectedCount" style="font-weight:800;font-size:14px;color:var(--muted);">0件選択中</span>
    <button type="button" class="button" id="saveCompaniesBtn" disabled>
      選択した0件をカンパニー化
    </button>
    <button type="button" class="button light" id="saveExcludeBtn" disabled
      style="color:#ef4444;border-color:#fca5a5;">
      選択した0件を除外
    </button>
    <button type="button" class="button" id="saveCompaniesExcludeAllBtn"
      style="background:#7c3aed;border-color:#7c3aed;">
      選択した0件をカンパニー化＋残り0件を除外
    </button>
    <div id="saveResult"></div>
  </div>

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

  document.getElementById('reSubmitBtn')?.addEventListener('click', function () {
    const form = document.getElementById('reSearchForm');
    if (!form) return;

    const formData = new FormData(form);
    const params   = new URLSearchParams();
    for (const [key, value] of formData.entries()) {
      if (key === '_token' || key === '_method') continue;
      params.append(key, value);
    }

    const modal  = document.getElementById('rePreviewModal');
    const bar    = document.getElementById('rePreviewBar');
    const status = document.getElementById('rePreviewStatus');
    const close  = document.getElementById('rePreviewClose');

    modal.style.display  = 'flex';
    bar.style.width      = '0%';
    status.textContent   = '接続中...';
    close.style.display  = 'none';
    this.disabled        = true;

    const es = new EventSource('/bizmaps/preview-stream?' + params.toString());

    es.onmessage = function (ev) {
      const data = JSON.parse(ev.data);

      if (data.finished) {
        es.close();
        bar.style.width    = '100%';
        status.textContent = 'スキャン済: ' + (data.scanned ?? 0) + '件 / 新規: ' + (data.main_count ?? 0) + '件';
        close.style.display = 'inline-block';
        close.textContent   = '結果を確認する';
        close.onclick       = () => { window.location.href = '/bizmaps/preview-result'; };
        return;
      }

      const pct  = data.total > 0 ? Math.round(((data.new_count ?? 0) / data.total) * 100) : 0;
      bar.style.width    = pct + '%';
      status.textContent = 'スキャン済: ' + (data.scanned ?? 0) + '件 / 新規: ' + (data.new_count ?? 0) + '件';
    };

    es.onerror = function () {
      es.close();
      status.textContent  = 'エラーが発生しました。もう一度お試しください。';
      close.style.display = 'inline-block';
      close.textContent   = '閉じる';
      close.onclick       = function () {
        modal.style.display                                     = 'none';
        document.getElementById('reSubmitBtn').disabled = false;
      };
    };
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
    const allChecks = document.querySelectorAll('.row-check');
    const count     = document.querySelectorAll('.row-check:checked').length;
    const total     = allChecks.length;
    const unchecked = total - count;
    const el = document.getElementById('selectedCount');
    if (el) el.textContent = count + '件選択中';
    const saveCompaniesBtn = document.getElementById('saveCompaniesBtn');
    if (saveCompaniesBtn) {
      saveCompaniesBtn.disabled = count === 0;
      saveCompaniesBtn.textContent = `選択した${count}件をカンパニー化`;
    }
    const saveExcludeBtn = document.getElementById('saveExcludeBtn');
    if (saveExcludeBtn) {
      saveExcludeBtn.disabled = count === 0;
      saveExcludeBtn.textContent = `選択した${count}件を除外`;
    }
    const saveAllBtn = document.getElementById('saveCompaniesExcludeAllBtn');
    if (saveAllBtn) {
      saveAllBtn.textContent = `選択した${count}件をカンパニー化＋残り${unchecked}件を除外`;
    }
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

  // ---- HP URL 取得（SSE + モーダル） ----
  const fetchHpBtn    = document.getElementById('fetchHpBtn');
  const fetchHpBtnText  = document.getElementById('fetchHpBtnText');
  const fetchHpProgress = document.getElementById('fetchHpProgress');
  const hpFoundBadge    = document.getElementById('hpFoundBadge');
  const hpFoundCountEl  = document.getElementById('hpFoundCount');
  const hpFetchModal  = document.getElementById('hpFetchModal');
  const hpModalBar    = document.getElementById('hpModalBar');
  const hpModalStatus = document.getElementById('hpModalStatus');
  const hpModalDetail = document.getElementById('hpModalDetail');
  const hpModalClose  = document.getElementById('hpModalClose');

  let sseActive    = false;
  let hpFoundCount = 0;

  if (fetchHpBtn) {
    fetchHpBtn.addEventListener('click', function () {
      if (sseActive) return;
      sseActive    = true;
      hpFoundCount = 0;

      // モーダルを開く
      hpFetchModal.style.display = 'flex';
      hpModalBar.style.width     = '0%';
      hpModalStatus.textContent  = '接続中...';
      hpModalDetail.textContent  = '';
      hpModalClose.style.display = 'none';

      fetchHpBtn.disabled = true;
      fetchHpBtnText.textContent = '取得中...';
      fetchHpProgress.style.display = 'none';

      const es = new EventSource('/bizmaps/fetch-hp-stream');

      es.onmessage = function (e) {
        const data = JSON.parse(e.data);

        if (data.finished) {
          es.close();
          sseActive = false;

          hpModalBar.style.width    = '100%';
          hpModalStatus.textContent = `完了: ${data.total}件中 ${hpFoundCount}件のHP URLを取得しました`;
          hpModalDetail.textContent = '';
          hpModalClose.style.display = 'inline-block';

          fetchHpBtnText.textContent    = 'HP URL取得完了';
          fetchHpProgress.style.display = 'inline';
          fetchHpProgress.textContent   = hpFoundCount + '件取得';
          return;
        }

        const pct = data.total > 0 ? Math.round((data.done / data.total) * 100) : 0;
        hpModalBar.style.width    = pct + '%';
        hpModalStatus.textContent = `処理中: ${data.company_name ?? ''} (${data.done}/${data.total})`;
        hpModalDetail.textContent = data.hp_url ? ('HP URL: ' + data.hp_url) : '';

        if (data.hp_url) {
          hpFoundCount++;
          if (hpFoundBadge)  hpFoundBadge.style.display = 'inline-flex';
          if (hpFoundCountEl) hpFoundCountEl.textContent = hpFoundCount;

          const idx     = data.index;
          const hpCell  = document.getElementById('hp-cell-' + idx);
          if (hpCell) {
            const disp = data.hp_url.length > 35 ? data.hp_url.substring(0, 35) + '…' : data.hp_url;
            hpCell.innerHTML = `<a href="${data.hp_url}" target="_blank" style="font-size:12px;color:var(--primary);word-break:break-all;text-decoration:none;font-weight:700;">${disp}</a>`;
          }
          const statusCell = document.getElementById('status-cell-' + idx);
          if (statusCell) statusCell.innerHTML = '<span class="badge green">HP✓</span>';

          if (PREVIEW_DATA[idx]) PREVIEW_DATA[idx].hp_url = data.hp_url;
          const cb = document.querySelector(`.row-check[value="${idx}"]`);
          if (cb && !PREVIEW_DATA[idx]?.is_duplicate) cb.checked = true;
          updateCount();
        }

        if (data.industry && PREVIEW_DATA[data.index]) {
          PREVIEW_DATA[data.index].industry = data.industry;
          const indCell = document.getElementById('industry-cell-' + data.index);
          if (indCell) {
            const t = data.industry;
            indCell.innerHTML = `<span style="font-size:12px;color:var(--muted);">${t.length > 25 ? t.substring(0,25) + '…' : t}</span>`;
          }
        }
      };

      es.onerror = function () {
        es.close();
        sseActive = false;
        hpModalStatus.textContent  = 'エラーが発生しました。もう一度お試しください。';
        hpModalDetail.textContent  = '';
        hpModalClose.style.display = 'inline-block';
        fetchHpBtn.disabled        = false;
        fetchHpBtnText.textContent = 'HP URLを取得する（再試行）';
      };
    });
  }

  if (hpModalClose) {
    hpModalClose.addEventListener('click', function () {
      hpFetchModal.style.display = 'none';
    });
  }

  // ---- カンパニー化・除外 ----
  const saveCompaniesBtn = document.getElementById('saveCompaniesBtn');
  const saveExcludeBtn   = document.getElementById('saveExcludeBtn');
  const saveResult       = document.getElementById('saveResult');

  function getCheckedItems() {
    const checked = document.querySelectorAll('.row-check:checked');
    return { checked, items: Array.from(checked).map(cb => PREVIEW_DATA[parseInt(cb.value)]) };
  }

  function markRowsDone(checked, badgeText) {
    checked.forEach(cb => {
      const row = document.getElementById('row-' + cb.value);
      if (row) row.style.opacity = '0.45';
      const span = document.createElement('span');
      span.className = 'badge gray';
      span.style.fontSize = '11px';
      span.textContent = badgeText;
      cb.replaceWith(span);
    });
    updateCount();
  }

  if (saveCompaniesBtn) {
    saveCompaniesBtn.addEventListener('click', function () {
      const { checked, items } = getCheckedItems();
      if (items.length === 0) return;

      this.disabled = true;
      this.textContent = '処理中...';
      saveResult.innerHTML = '';

      fetch('/bizmaps/store-companies', {
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
          `<span class="badge green" style="font-size:13px;padding:8px 14px;">カンパニー化 ${data.saved}件</span>` +
          (data.skipped > 0 ? ` <span class="badge gray" style="font-size:12px;">スキップ ${data.skipped}件</span>` : '');
        markRowsDone(checked, '登録済');
      })
      .catch(err => {
        saveResult.innerHTML = `<span class="badge red">失敗: ${err.message}</span>`;
        this.disabled = false;
        updateCount();
      });
    });
  }

  if (saveExcludeBtn) {
    saveExcludeBtn.addEventListener('click', function () {
      const { checked, items } = getCheckedItems();
      if (items.length === 0) return;

      const excludedDetailUrls = items.map(item => item.detail_url).filter(Boolean);

      this.disabled = true;
      this.textContent = '処理中...';
      saveResult.innerHTML = '';

      fetch('/bizmaps/store-with-exclusion', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ items, excluded_detail_urls: excludedDetailUrls }),
      })
      .then(r => r.json())
      .then(data => {
        saveResult.innerHTML =
          `<span class="badge gray" style="font-size:13px;padding:8px 14px;">除外登録 ${data.saved_excluded}件</span>` +
          (data.skipped > 0 ? ` <span class="badge amber" style="font-size:12px;">スキップ ${data.skipped}件</span>` : '');
        markRowsDone(checked, '除外済');
      })
      .catch(err => {
        saveResult.innerHTML = `<span class="badge red">失敗: ${err.message}</span>`;
        this.disabled = false;
        updateCount();
      });
    });
  }

  const saveAllBtn = document.getElementById('saveCompaniesExcludeAllBtn');
  if (saveAllBtn) {
    saveAllBtn.addEventListener('click', function () {
      const allChecks  = document.querySelectorAll('.row-check');
      const storeItems = [];
      const excludeItems = [];

      allChecks.forEach(cb => {
        const item = PREVIEW_DATA[parseInt(cb.value)];
        if (!item || (item.is_duplicate)) return;
        if (cb.checked) {
          storeItems.push(item);
        } else {
          excludeItems.push(item);
        }
      });

      if (storeItems.length === 0 && excludeItems.length === 0) {
        alert('処理対象がありません');
        return;
      }

      if (!confirm(`選択した${storeItems.length}件をカンパニー化＋残り${excludeItems.length}件を除外します。よろしいですか？`)) return;

      this.disabled = true;
      this.textContent = '処理中...';
      saveResult.innerHTML = '';

      fetch('/bizmaps/store-with-exclusion-all', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ store_items: storeItems, exclude_items: excludeItems }),
      })
      .then(r => r.json())
      .then(data => {
        saveResult.innerHTML =
          `<span class="badge green" style="font-size:13px;padding:8px 14px;">カンパニー化 ${data.saved_companies}件</span>` +
          ` <span class="badge gray" style="font-size:13px;padding:8px 14px;">除外 ${data.saved_excluded}件</span>` +
          (data.skipped > 0 ? ` <span class="badge amber" style="font-size:12px;">スキップ ${data.skipped}件</span>` : '');
        allChecks.forEach(cb => {
          const row = document.getElementById('row-' + cb.value);
          if (row) row.style.opacity = '0.45';
          const span = document.createElement('span');
          span.className = 'badge gray';
          span.style.fontSize = '11px';
          span.textContent = cb.checked ? '登録済' : '除外済';
          cb.replaceWith(span);
        });
        updateCount();
      })
      .catch(err => {
        saveResult.innerHTML = `<span class="badge red">失敗: ${err.message}</span>`;
        this.disabled = false;
        updateCount();
      });
    });
  }

});
</script>
@endverbatim
@endpush
