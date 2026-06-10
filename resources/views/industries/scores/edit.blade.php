@extends('layouts.app', ['title' => '業界スコア編集'])

@section('content')
    <main class="content">
        <div class="card" style="margin-bottom: 20px;">
            <p class="page-kicker">INDUSTRY SCORE EDIT</p>
            <div class="row" style="align-items: flex-start;">
                <div>
                    <h1 class="page-title">{{ $industry->name }}</h1>
                    <p class="page-subtitle">
                        {{ $industry->slug }} の業界スコアを編集。これは研究用の箱であり、個社スコア・営業候補ランキングにはまだ反映しない。
                    </p>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('industries.scores.index') }}">一覧へ戻る</a>
                </div>
            </div>
        </div>

        @if ($errors->any())
            <div class="error">
                <strong>保存できませんでした。</strong>
                <ul style="margin: 8px 0 0; padding-left: 20px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('industries.scores.update', $industry->slug) }}">
            @csrf
            @method('PUT')

            @foreach ($axesByCategory as $category => $axes)
                <div class="card" style="margin-bottom: 20px;">
                    <div class="row" style="margin-bottom: 18px; align-items: flex-end;">
                        <div>
                            <p class="page-kicker" style="margin-bottom: 4px;">{{ strtoupper($category) }}</p>
                            <h2 style="margin: 0; font-size: 22px;">{{ $categoryLabels[$category] ?? $category }}</h2>
                        </div>
                        <div class="muted" style="font-size: 13px;">0=低い / 5=高い / 未設定可</div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th style="min-width: 240px;">軸</th>
                                    <th style="width: 130px;">値</th>
                                    <th style="width: 140px;">信頼度</th>
                                    <th style="width: 140px;">種別</th>
                                    <th>メモ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($axes as $axis)
                                    @php($score = $scores->get($axis->key))
                                    <tr>
                                        <td>
                                            <div style="font-weight: 900;">{{ $axis->label }}</div>
                                            <div class="muted" style="font-size: 12px; margin-top: 4px;">{{ $axis->key }}</div>
                                            @if ($axis->description)
                                                <div class="muted" style="font-size: 13px; line-height: 1.6; margin-top: 8px;">{{ $axis->description }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <select name="scores[{{ $axis->key }}][value]">
                                                <option value="">未設定</option>
                                                @for ($i = $axis->min_value; $i <= $axis->max_value; $i++)
                                                    <option value="{{ $i }}" @selected((string) old("scores.{$axis->key}.value", $score?->value) === (string) $i)>{{ $i }}</option>
                                                @endfor
                                            </select>
                                        </td>
                                        <td>
                                            <select name="scores[{{ $axis->key }}][confidence]">
                                                @foreach ($confidences as $value => $label)
                                                    <option value="{{ $value }}" @selected(old("scores.{$axis->key}.confidence", $score?->confidence ?? '') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select name="scores[{{ $axis->key }}][score_type]">
                                                @foreach ($scoreTypes as $value => $label)
                                                    <option value="{{ $value }}" @selected(old("scores.{$axis->key}.score_type", $score?->score_type ?? 'hypothesis') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <textarea name="scores[{{ $axis->key }}][note]" rows="3" placeholder="判断理由・観測メモ・後で見直す前提など">{{ old("scores.{$axis->key}.note", $score?->note) }}</textarea>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            <div class="card">
                <div class="row">
                    <p class="muted" style="margin: 0; line-height: 1.7;">
                        保存しても営業候補一覧には反映しない。実データを回しながら、仮説値を実測値へ更新していくための編集箱。
                    </p>
                    <div class="actions">
                        <a class="button light" href="{{ route('industries.scores.index') }}">キャンセル</a>
                        <button class="button" type="submit">業界スコアを保存</button>
                    </div>
                </div>
            </div>
        </form>
    </main>
@endsection
