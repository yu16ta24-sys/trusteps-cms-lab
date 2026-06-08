@extends('layouts.app', ['title' => 'company詳細 | TRUSTEPS CMS Lab'])

@section('content')
    <main class="content">
        <section class="card">
            <div class="row">
                <div>
                    <p class="muted" style="margin:0;">Company #{{ $company->id }}</p>
                    <h1 style="margin:6px 0 0;">{{ $company->display_name }}</h1>
                </div>
                <div class="actions">
                    <a class="button light" href="{{ route('companies.index') }}">companies一覧へ</a>
                    <a class="button light" href="{{ route('source-records.index') }}">source_recordsへ</a>
                    @if ($company->status !== 'merged')
                        <a class="button" href="{{ route('companies.merge-form', $company) }}">このcompanyを統合</a>
                    @else
                        <form method="POST" action="{{ route('companies.undo-merge', $company) }}" onsubmit="return confirm('このcompanyの統合をUndoする？');">
                            @csrf
                            <button class="button danger" type="submit">統合をUndo</button>
                        </form>
                    @endif
                </div>
            </div>

            @if (session('status'))
                <div class="status" style="margin-top:20px;">{{ session('status') }}</div>
            @endif

            @if ($company->status === 'merged')
                <div class="error" style="margin-top:20px;">
                    このcompanyは統合済み。統合先：
                    @if ($company->mergedInto)
                        <a href="{{ route('companies.show', $company->mergedInto) }}">#{{ $company->mergedInto->id }} {{ $company->mergedInto->display_name }}</a>
                    @else
                        不明
                    @endif
                </div>
            @endif



            <div class="card" style="box-shadow:none; margin-top:20px;">
                <div class="row">
                    <div>
                        <h2 style="margin:0;">4軸スコア</h2>
                        <p class="muted" style="margin:6px 0 0;">機会2軸・リスク2軸を手動評価する。機会とリスクは合算しない。</p>
                    </div>
                    <span class="badge gray">algo_version=v1</span>
                </div>

                @php
                    $hpWeakness = optional($scoresByAxis->get('hp_weakness'))->value;
                    $selfUpdateFit = optional($scoresByAxis->get('self_update_fit'))->value;
                    $devDifficulty = optional($scoresByAxis->get('dev_difficulty'))->value;
                    $portalDependence = optional($scoresByAxis->get('portal_dependence'))->value;

                    $scoredAxesCount = collect([$hpWeakness, $selfUpdateFit, $devDifficulty, $portalDependence])
                        ->filter(fn ($value) => $value !== null)
                        ->count();

                    $opportunityScore = ($hpWeakness ?? 0) + ($selfUpdateFit ?? 0);
                    $riskScore = ($devDifficulty ?? 0) + ($portalDependence ?? 0);

                    if ($scoredAxesCount < 4) {
                        $scoreJudgment = '未採点あり';
                        $scoreJudgmentClass = 'gray';
                    } elseif ($opportunityScore >= 7 && $riskScore <= 3) {
                        $scoreJudgment = '高機会・低リスク';
                        $scoreJudgmentClass = 'green';
                    } elseif ($opportunityScore >= 7 && $riskScore >= 7) {
                        $scoreJudgment = '高機会・高リスク';
                        $scoreJudgmentClass = 'gray';
                    } elseif ($opportunityScore <= 3 && $riskScore >= 7) {
                        $scoreJudgment = '低機会・高リスク';
                        $scoreJudgmentClass = 'red';
                    } elseif ($opportunityScore <= 3 && $riskScore <= 3) {
                        $scoreJudgment = '低機会・低リスク';
                        $scoreJudgmentClass = 'gray';
                    } else {
                        $scoreJudgment = '要確認';
                        $scoreJudgmentClass = 'gray';
                    }
                @endphp

                <div class="grid" style="margin-top:18px;">
                    <div class="mini-card" style="background:#f0fdf4;">
                        <span class="badge green">機会</span>
                        <h3 style="margin:10px 0 4px; font-size:28px;">{{ $opportunityScore }} / 10</h3>
                        <p class="muted" style="margin:0;">hp_weakness + self_update_fit</p>
                    </div>
                    <div class="mini-card" style="background:#fff7ed;">
                        <span class="badge gray">リスク</span>
                        <h3 style="margin:10px 0 4px; font-size:28px;">{{ $riskScore }} / 10</h3>
                        <p class="muted" style="margin:0;">dev_difficulty + portal_dependence</p>
                    </div>
                    <div class="mini-card">
                        <span class="badge {{ $scoreJudgmentClass }}">簡易判定</span>
                        <h3 style="margin:10px 0 4px; font-size:24px;">{{ $scoreJudgment }}</h3>
                        <p class="muted" style="margin:0;">採点済み：{{ $scoredAxesCount }} / 4軸</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('companies.scores.store', $company) }}" style="margin-top:18px;">
                    @csrf

                    <div class="grid">
                        @foreach ($scoreAxes as $axis => $meta)
                            @php
                                $currentScore = $scoresByAxis->get($axis);
                                $currentReason = $currentScore?->reason_json ?? [];
                                $currentNote = old("scores.$axis.note", $currentReason['note'] ?? '');
                                $currentValue = old("scores.$axis.value", $currentScore?->value ?? 0);
                                $currentConfidence = old("scores.$axis.confidence", $currentScore?->confidence ?? '0.6');
                                $isRisk = $meta['group'] === 'リスク';
                            @endphp

                            <div class="mini-card" style="background:{{ $isRisk ? '#fff7ed' : '#f8fafc' }};">
                                <div class="row" style="align-items:flex-start;">
                                    <div>
                                        <strong>{{ $axis }}</strong>
                                        <div style="font-weight:700;">{{ $meta['label'] }}</div>
                                    </div>
                                    <span class="badge {{ $isRisk ? 'gray' : 'green' }}">{{ $meta['group'] }}</span>
                                </div>

                                <p class="muted" style="font-size:13px;">{{ $meta['description'] }}</p>
                                <p class="muted" style="font-size:12px; margin-bottom:8px;">
                                    0：{{ $meta['anchor_0'] }}<br>
                                    3：{{ $meta['anchor_3'] }}<br>
                                    5：{{ $meta['anchor_5'] }}
                                </p>

                                <div class="field">
                                    <label for="score_{{ $axis }}_value">value 0〜5（{{ $meta['polarity'] }}）</label>
                                    <select id="score_{{ $axis }}_value" name="scores[{{ $axis }}][value]" required>
                                        @for ($i = 0; $i <= 5; $i++)
                                            <option value="{{ $i }}" @selected((string) $currentValue === (string) $i)>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="score_{{ $axis }}_confidence">confidence</label>
                                    <select id="score_{{ $axis }}_confidence" name="scores[{{ $axis }}][confidence]" required>
                                        <option value="0.3" @selected((string) $currentConfidence === '0.3')>0.3：推測中心</option>
                                        <option value="0.6" @selected((string) $currentConfidence === '0.6')>0.6：一部確認</option>
                                        <option value="0.9" @selected((string) $currentConfidence === '0.9')>0.9：直接確認</option>
                                    </select>
                                </div>

                                <div class="field" style="margin-bottom:0;">
                                    <label for="score_{{ $axis }}_note">判断メモ</label>
                                    <textarea id="score_{{ $axis }}_note" name="scores[{{ $axis }}][note]" placeholder="何を見てその点数にしたか">{{ $currentNote }}</textarea>
                                </div>

                                @if ($currentScore)
                                    <div style="margin-top:12px; padding:10px; border-radius:12px; background:#ffffff; border:1px solid #d1d5db;">
                                        <div style="font-size:20px; font-weight:800;">現在：{{ $currentScore->value }}点</div>
                                        <div class="muted" style="font-size:12px; margin-top:4px;">
                                            confidence {{ $currentScore->confidence }}<br>
                                            scored_by：{{ $currentScore->scored_by ?? '-' }}<br>
                                            scored_at：{{ optional($currentScore->scored_at)->format('Y-m-d H:i:s') ?? '-' }}
                                        </div>
                                    </div>
                                @else
                                    <div style="margin-top:12px; padding:10px; border-radius:12px; background:#ffffff; border:1px solid #e5e7eb;">
                                        <div class="muted" style="font-size:14px;">未採点</div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div style="margin-top:18px;">
                        <button class="button" type="submit">4軸スコアを保存</button>
                    </div>
                </form>
            </div>

            <div class="card" style="box-shadow:none; margin-top:20px;">
                <div class="row">
                    <div>
                        <h2 style="margin:0;">kill_flags</h2>
                        <p class="muted" style="margin:6px 0 0;">手動で即時除外理由を付ける。1つでもflagがあれば is_killed=true。</p>
                    </div>
                    <span class="badge {{ $company->is_killed ? 'gray' : 'green' }}">
                        is_killed={{ $company->is_killed ? 'true' : 'false' }}
                    </span>
                </div>

                <div class="table-wrap" style="margin-top:16px;">
                    <table>
                        <thead>
                        <tr>
                            <th>flag</th>
                            <th>note</th>
                            <th>source</th>
                            <th>flagged_by</th>
                            <th>flagged_at</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($company->killFlags as $killFlag)
                            <tr>
                                <td><strong>{{ $killFlag->flag }}</strong></td>
                                <td>{{ $killFlag->note ?? '-' }}</td>
                                <td>{{ $killFlag->source ?? '-' }}</td>
                                <td>{{ $killFlag->flagged_by ?? '-' }}</td>
                                <td>{{ optional($killFlag->flagged_at)->format('Y-m-d H:i:s') ?? '-' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('companies.kill-flags.destroy', [$company, $killFlag]) }}" onsubmit="return confirm('このkill_flagを解除する？');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button small danger" type="submit">解除</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="muted">kill_flagなし</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <form method="POST" action="{{ route('companies.kill-flags.store', $company) }}" style="margin-top:18px;">
                    @csrf
                    <div class="grid">
                        <div class="field" style="margin-bottom:0;">
                            <label for="flag">追加するkill_flag</label>
                            <select id="flag" name="flag" required>
                                <option value="">選択</option>
                                <option value="no_official_site">no_official_site：公式HPなし</option>
                                <option value="defunct">defunct：活動停止・閉業</option>
                                <option value="chain_no_edit_rights">chain_no_edit_rights：ローカル編集権限なし</option>
                                <option value="out_of_scope_size">out_of_scope_size：対象外規模</option>
                                <option value="compliance_risk">compliance_risk：コンプライアンス・対象外属性</option>
                            </select>
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label for="note">note</label>
                            <input id="note" name="note" type="text" placeholder="何を見て判断したか">
                        </div>
                        <div class="field" style="margin-bottom:0; align-self:end;">
                            <button class="button danger" type="submit">kill_flag追加</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="table-wrap" style="margin-top:24px;">
                <table>
                    <tbody>
                    <tr><th>ID</th><td>{{ $company->id }}</td></tr>
                    <tr><th>status</th><td><span class="badge gray">{{ $company->status }}</span></td></tr>
                    <tr><th>merge_previous_status</th><td>{{ $company->merge_previous_status ?? '-' }}</td></tr>
                    <tr><th>merged_into</th><td>{{ $company->mergedInto ? '#' . $company->mergedInto->id . ' ' . $company->mergedInto->display_name : '-' }}</td></tr>
                    <tr><th>merged_at</th><td>{{ optional($company->merged_at)->format('Y-m-d H:i:s') ?? '-' }}</td></tr>
                    <tr><th>merged_by</th><td>{{ $company->merged_by ?? '-' }}</td></tr>
                    <tr><th>merge_reason</th><td>{{ $company->merge_reason ?? '-' }}</td></tr>
                    <tr><th>display_name</th><td>{{ $company->display_name }}</td></tr>
                    <tr><th>legal_name</th><td>{{ $company->legal_name ?? '-' }}</td></tr>
                    <tr><th>name_norm</th><td>{{ $company->name_norm ?? '-' }}</td></tr>
                    <tr><th>industry</th><td>{{ $company->industry?->name ?? '-' }}</td></tr>
                    <tr><th>municipality</th><td>{{ $company->municipality?->prefecture?->name ?? $company->pref ?? '-' }} / {{ $company->municipality?->name ?? $company->city ?? '-' }}</td></tr>
                    <tr><th>corporate_number</th><td>{{ $company->corporate_number ?? '-' }}</td></tr>
                    <tr><th>primary_domain</th><td>{{ $company->primaryDomain?->url ?? '-' }} / {{ $company->primaryDomain?->normalized_domain ?? '-' }}</td></tr>
                    <tr><th>is_killed</th><td>{{ $company->is_killed ? 'true' : 'false' }}</td></tr>
                    <tr><th>created_at</th><td>{{ optional($company->created_at)->format('Y-m-d H:i:s') ?? '-' }}</td></tr>
                    </tbody>
                </table>
            </div>

            @if ($company->mergedChildren->count())
                <h2>このcompanyに統合されたcompany</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>display_name</th>
                            <th>status</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($company->mergedChildren as $child)
                            <tr>
                                <td>{{ $child->id }}</td>
                                <td>{{ $child->display_name }}</td>
                                <td>{{ $child->status }}</td>
                                <td><a class="button small light" href="{{ route('companies.show', $child) }}">詳細</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <h2>domains</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>url</th>
                        <th>normalized_domain</th>
                        <th>role</th>
                        <th>primary</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($company->domains as $domain)
                        <tr>
                            <td>{{ $domain->id }}</td>
                            <td style="overflow-wrap:anywhere;">{{ $domain->url }}</td>
                            <td>{{ $domain->normalized_domain ?? '-' }}</td>
                            <td>{{ $domain->role }}</td>
                            <td>{{ $domain->is_primary ? 'true' : 'false' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">domainなし</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <h2>source links</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>source_record_id</th>
                        <th>match_type</th>
                        <th>source_type</th>
                        <th>domain</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($company->sourceLinks as $link)
                        <tr>
                            <td>{{ $link->source_record_id }}</td>
                            <td>{{ $link->match_type }}</td>
                            <td>{{ $link->sourceRecord?->source_type ?? '-' }}</td>
                            <td>{{ $link->sourceRecord?->normalized_domain ?? '-' }}</td>
                            <td>
                                @if ($link->sourceRecord)
                                    <a class="button small light" href="{{ route('source-records.show', $link->sourceRecord) }}">sourceを見る</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">source linkなし</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
@endsection
