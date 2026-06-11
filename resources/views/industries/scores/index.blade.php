@extends('layouts.app', ['title' => '業界スコア'])

@section('content')
<div class="content">

  {{-- ヘッダー --}}
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;flex-wrap:wrap;gap:12px;">
    <div>
      <p class="page-kicker">Industry Scores</p>
      <h1 class="page-title">業界スコア</h1>
      <p class="page-subtitle">業種ごとのCMS事業適性・参入余白を仮説/実測メモとして管理する。個社スコアや営業候補ランキングへの反映は今後。</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
      <div class="mini-card" style="text-align:center;padding:14px 20px;">
        <div style="font-size:11px;font-weight:900;letter-spacing:.08em;color:var(--muted);">AXES</div>
        <div style="font-size:28px;font-weight:900;margin-top:4px;">{{ $axes->count() }}</div>
        <div style="font-size:12px;color:var(--muted);">有効スコア軸</div>
      </div>
      <a class="button light small" href="{{ route('dashboard') }}">Dashboard</a>
    </div>
  </div>

  @if(session('status'))
    <div class="alert-box status" style="margin-bottom:20px;">{{ session('status') }}</div>
  @endif

  {{-- 大分類ループ --}}
  @foreach($parents as $parent)
    @php $parentChildren = $children->get($parent->id, collect()); @endphp

    <details class="form-section" style="margin-bottom:16px;" open>
      <summary style="cursor:pointer;list-style:none;display:flex;align-items:center;gap:12px;padding:4px 0;">
        <span style="font-size:17px;font-weight:950;letter-spacing:-.02em;">{{ $parent->name }}</span>
        <span class="badge gray" style="font-size:11px;">{{ $parentChildren->count() }}種</span>
        @php
          $settledCount = $parentChildren->filter(fn($c) => ($summaries[$c->slug]['filled_count'] ?? 0) > 0)->count();
        @endphp
        @if($settledCount > 0)
          <span class="badge green" style="font-size:11px;">{{ $settledCount }}種設定済</span>
        @endif
        <span style="margin-left:auto;font-size:11px;color:var(--muted);">▼</span>
      </summary>

      @if($parentChildren->isEmpty())
        <div style="padding:12px 0;color:var(--muted);font-size:13px;">中分類なし</div>
      @else
        <div class="table-wrap" style="margin-top:12px;">
          <table>
            <thead>
              <tr>
                <th>中分類</th>
                @foreach($categoryKeys as $category)
                  <th>{{ $categoryLabels[$category] ?? $category }}</th>
                @endforeach
                <th class="tight">入力済み</th>
                <th class="tight">最終更新</th>
                <th class="tight">操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach($parentChildren as $child)
                @php
                  $summary = $summaries[$child->slug] ?? ['filled_count' => 0, 'updated_at' => null, 'categories' => []];
                  $totalAxes = $axes->count();
                @endphp
                <tr>
                  <td>
                    <div style="font-weight:800;font-size:14px;">{{ $child->name }}</div>
                    <div style="font-size:11px;color:var(--muted);font-family:monospace;">{{ $child->slug }}</div>
                  </td>
                  @foreach($categoryKeys as $category)
                    <td class="tight" style="text-align:center;">
                      @php $val = $summary['categories'][$category] ?? null; @endphp
                      @if($val !== null)
                        <span style="display:inline-flex;align-items:center;justify-content:center;
                          width:36px;height:36px;border-radius:999px;font-weight:900;font-size:14px;
                          background:{{ $val >= 4 ? '#dcfce7' : ($val >= 2.5 ? '#fef3c7' : '#fee2e2') }};
                          color:{{ $val >= 4 ? '#166534' : ($val >= 2.5 ? '#92400e' : '#991b1b') }};">
                          {{ $val }}
                        </span>
                      @else
                        <span style="color:#d0d5dd;font-size:12px;">—</span>
                      @endif
                    </td>
                  @endforeach
                  <td class="tight" style="text-align:center;">
                    @if($summary['filled_count'] > 0)
                      <span class="badge {{ $summary['filled_count'] >= $totalAxes ? 'green' : 'amber' }}" style="font-size:11px;">
                        {{ $summary['filled_count'] }} / {{ $totalAxes }}
                      </span>
                    @else
                      <span style="color:#d0d5dd;font-size:12px;">未設定</span>
                    @endif
                  </td>
                  <td class="tight" style="white-space:nowrap;font-size:12px;color:var(--muted);">
                    {{ $summary['updated_at'] ?? '—' }}
                  </td>
                  <td class="tight">
                    <a href="{{ route('industries.scores.edit', $child->slug) }}" class="button small light">編集</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </details>
  @endforeach

</div>
@endsection
