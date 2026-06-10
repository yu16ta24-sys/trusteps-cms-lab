@extends('layouts.app', ['title' => '業界スコア'])

@section('content')
    <main class="content">
        <div class="card" style="margin-bottom: 20px;">
            <p class="page-kicker">INDUSTRY SCORES</p>
            <div class="row" style="align-items: flex-start;">
                <div>
                    <h1 class="page-title">業界スコア</h1>
                    <p class="page-subtitle">
                        業界ごとのCMS事業適性・参入余白を、仮説/実測メモとして管理する箱。個社スコアや営業候補ランキングにはまだ反映しない。
                    </p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('dashboard') }}">Dashboard</a>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        <div class="card" style="margin-bottom: 20px;">
            <div class="row" style="align-items: flex-start;">
                <div>
                    <h2 style="margin: 0 0 8px; font-size: 20px;">v0.18.1 方針</h2>
                    <p class="muted" style="margin: 0; line-height: 1.8;">
                        最初は0〜5点の手動編集だけ。総合点は出さず、機会・余白・実行・リスクのカテゴリ別状態を確認する。
                        軸はseedで追加し、後から増減できる縦持ち設計。
                    </p>
                </div>
                <div class="mini-card" style="min-width: 220px;">
                    <div class="muted" style="font-size: 12px; font-weight: 900; letter-spacing: .08em;">AXES</div>
                    <div style="font-size: 28px; font-weight: 900; margin-top: 6px;">{{ $axes->count() }}</div>
                    <div class="muted" style="font-size: 13px;">有効スコア軸</div>
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>業種</th>
                        @foreach ($categoryKeys as $category)
                            <th>{{ $categoryLabels[$category] ?? $category }}</th>
                        @endforeach
                        <th>入力済み</th>
                        <th>最終更新</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($industries as $industry)
                        @php($summary = $summaries[$industry->slug] ?? ['categories' => [], 'filled_count' => 0, 'updated_at' => null])
                        <tr>
                            <td>
                                <div style="font-weight: 900;">{{ $industry->name }}</div>
                                <div class="muted" style="font-size: 12px; margin-top: 4px;">{{ $industry->slug }}</div>
                            </td>
                            @foreach ($categoryKeys as $category)
                                @php($value = $summary['categories'][$category] ?? null)
                                <td>
                                    @if ($value === null)
                                        <span class="muted">未設定</span>
                                    @else
                                        <span class="badge gray">{{ number_format($value, 1) }} / 5</span>
                                    @endif
                                </td>
                            @endforeach
                            <td>{{ $summary['filled_count'] }} / {{ $axes->count() }}</td>
                            <td>{{ $summary['updated_at'] ?? '—' }}</td>
                            <td>
                                <a class="button small" href="{{ route('industries.scores.edit', $industry->slug) }}">編集</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 5 + $categoryKeys->count() }}" class="muted">業種マスタがまだありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
@endsection
