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
          <td style="max-width:140px;">
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

  // ---- SSE: HP URL リアルタイム取得 ----
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
