@extends('layouts.app')

@section('title', 'BIZMAPSインポート - プレビュー')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">BIZMAPSインポート - プレビュー</h1>
    <a href="{{ route('bizmaps.import') }}" class="btn btn-outline-secondary btn-sm">← 条件に戻る</a>
  </div>

  <div class="mb-3 d-flex gap-3 align-items-center">
    <span class="text-muted">取得件数: <strong>{{ count($results) }}</strong>件</span>
    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAll">全選択</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="selectNone">全解除</button>
    <button type="button" class="btn btn-sm btn-outline-success" id="selectHpOnly">HP URLありのみ選択</button>
  </div>

  @if(empty($results))
    <div class="alert alert-warning">
      該当する企業が取得できませんでした。条件を変えて再度お試しください。
    </div>
  @else

  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle" id="previewTable">
      <thead class="table-light">
        <tr>
          <th width="40"><input type="checkbox" id="checkAll"></th>
          <th>会社名</th>
          <th>都道府県</th>
          <th>市区町村</th>
          <th>業種</th>
          <th>HP URL</th>
          <th>詳細ページ</th>
          <th>状態</th>
        </tr>
      </thead>
      <tbody>
        @foreach($results as $i => $row)
        <tr class="{{ $row['is_duplicate'] ? 'table-secondary' : '' }}">
          <td>
            @if($row['is_duplicate'])
              <span class="badge bg-secondary">保存済</span>
            @else
              <input type="checkbox" class="row-check" value="{{ $i }}"
                {{ $row['hp_url'] ? 'checked' : '' }}>
            @endif
          </td>
          <td class="fw-semibold">{{ $row['name'] ?? '-' }}</td>
          <td>{{ $row['pref'] ?? '-' }}</td>
          <td>{{ $row['city'] ?? '-' }}</td>
          <td><small class="text-muted">{{ Str::limit($row['industry'] ?? '-', 30) }}</small></td>
          <td>
            @if($row['hp_url'])
              <a href="{{ $row['hp_url'] }}" target="_blank" class="text-break small">
                {{ Str::limit($row['hp_url'], 40) }}
              </a>
            @else
              <span class="text-muted small">-</span>
            @endif
          </td>
          <td>
            <a href="{{ $row['detail_url'] }}" target="_blank" class="small">BIZMAPS</a>
          </td>
          <td>
            @if($row['is_duplicate'])
              <span class="badge bg-secondary">重複</span>
            @elseif($row['hp_url'])
              <span class="badge bg-success">HP取得済</span>
            @else
              <span class="badge bg-warning text-dark">HPなし</span>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="mt-3 d-flex gap-3 align-items-center">
    <span id="selectedCount" class="text-muted">0件選択中</span>
    <button type="button" class="btn btn-primary px-5" id="saveBtn">
      選択した企業をsource_recordsに保存
    </button>
    <div id="saveResult" class="ms-3"></div>
  </div>

  @endif

</div>

{{-- 結果データをJSに渡す --}}
<script>
const PREVIEW_DATA = @json($results);
</script>

@push('scripts')
<script>
@verbatim
document.addEventListener('DOMContentLoaded', function () {

  // 全選択チェックボックス
  document.getElementById('checkAll').addEventListener('change', function () {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    updateCount();
  });

  document.getElementById('selectAll').addEventListener('click', function () {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = true);
    updateCount();
  });

  document.getElementById('selectNone').addEventListener('click', function () {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    updateCount();
  });

  document.getElementById('selectHpOnly').addEventListener('click', function () {
    document.querySelectorAll('.row-check').forEach((cb, i) => {
      cb.checked = !!PREVIEW_DATA[parseInt(cb.value)]?.hp_url;
    });
    updateCount();
  });

  document.querySelectorAll('.row-check').forEach(cb => {
    cb.addEventListener('change', updateCount);
  });

  function updateCount() {
    const count = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('selectedCount').textContent = count + '件選択中';
  }

  // 初期カウント
  updateCount();

  // 保存ボタン
  document.getElementById('saveBtn').addEventListener('click', function () {
    const checked = document.querySelectorAll('.row-check:checked');
    if (checked.length === 0) {
      alert('保存する企業を選択してください');
      return;
    }

    const items = Array.from(checked).map(cb => {
      return PREVIEW_DATA[parseInt(cb.value)];
    });

    this.disabled = true;
    this.textContent = '保存中...';

    fetch('{{ route("bizmaps.store") }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({ items }),
    })
    .then(r => r.json())
    .then(data => {
      document.getElementById('saveResult').innerHTML =
        `<span class="text-success fw-bold">保存完了: ${data.saved}件</span>` +
        (data.skipped > 0 ? ` <span class="text-muted">（スキップ: ${data.skipped}件）</span>` : '');

      // 保存済み行をグレーアウト
      checked.forEach(cb => {
        cb.closest('tr').classList.add('table-secondary');
        cb.replaceWith(document.createTextNode('✓'));
      });

      this.disabled = false;
      this.textContent = '選択した企業をsource_recordsに保存';
      updateCount();
    })
    .catch(err => {
      document.getElementById('saveResult').innerHTML =
        '<span class="text-danger">保存失敗: ' + err.message + '</span>';
      this.disabled = false;
      this.textContent = '選択した企業をsource_recordsに保存';
    });
  });

});
@endverbatim
</script>
@endpush
@endsection
