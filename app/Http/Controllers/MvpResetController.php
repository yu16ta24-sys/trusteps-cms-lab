<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class MvpResetController extends Controller
{
    private const SESSION_KEY = 'cms_lab_mvp_reset_token';

    /**
     * Operational MVP data only. Master/config tables are intentionally excluded.
     * Keep this order child-first for database engines where FK checks are not disabled.
     *
     * @return array<string, array{label: string, note: string}>
     */
    private function resetTableDefinitions(): array
    {
        return [
            'judgment_reason_links' => [
                'label' => '判定理由リンク',
                'note' => 'judgments と reason_codes の紐付け。reason_codes本体は残す。',
            ],
            'judgments' => [
                'label' => '営業判定',
                'note' => '送る/送らない/保留などのMVP中の判断履歴。',
            ],
            'company_kill_flags' => [
                'label' => 'kill flags',
                'note' => 'no_official_site等の個社除外フラグ。',
            ],
            'company_scores' => [
                'label' => '個社スコア',
                'note' => 'hp_weakness/self_update_fit等の会社別スコア。',
            ],
            'snapshot_update_targets' => [
                'label' => '更新対象検出',
                'note' => 'HPスナップショットに紐づく更新対象の観測結果。',
            ],
            'hp_facts' => [
                'label' => 'HP facts',
                'note' => 'CMS/SSL/更新状態などのHP解析結果。',
            ],
            'hp_snapshots' => [
                'label' => 'HP snapshots',
                'note' => 'クロール・手動観測のスナップショット記録。',
            ],
            'domains' => [
                'label' => 'domains',
                'note' => '会社に紐づく公式/ポータル等のURL。',
            ],
            'resolution_decisions' => [
                'label' => '名寄せ判断',
                'note' => 'source_record と company の同一/別/不明判断。',
            ],
            'company_source_links' => [
                'label' => '会社-sourceリンク',
                'note' => 'source_records と companies の確定紐付け。',
            ],
            'companies' => [
                'label' => 'companies',
                'note' => 'MVP中に作成した会社候補・確定会社。',
            ],
            'source_records' => [
                'label' => 'source_records',
                'note' => '候補収集ラボ/CSV等で投入した元データ。',
            ],
        ];
    }

    public function show(): View
    {
        return view('system.reset-mvp-data', [
            'stage' => 'start',
            'counts' => $this->buildCounts(),
            'token' => null,
            'total' => $this->totalCount($this->buildCounts()),
        ]);
    }

    public function preview(Request $request): View
    {
        $counts = $this->buildCounts();
        $token = (string) Str::uuid();

        session()->put(self::SESSION_KEY, [
            'token' => $token,
            'created_at' => now()->timestamp,
        ]);

        return view('system.reset-mvp-data', [
            'stage' => 'preview',
            'counts' => $counts,
            'token' => $token,
            'total' => $this->totalCount($counts),
        ]);
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        $token = (string) $request->input('reset_token', '');

        if (!$this->isValidToken($token)) {
            return redirect()
                ->route('system.reset-mvp-data.index')
                ->withErrors(['reset_token' => '確認トークンが切れている。もう一度、件数確認からやり直して。']);
        }

        $counts = $this->buildCounts();

        return view('system.reset-mvp-data', [
            'stage' => 'confirm',
            'counts' => $counts,
            'token' => $token,
            'total' => $this->totalCount($counts),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reset_token' => ['required', 'string'],
            'final_confirm' => ['accepted'],
        ]);

        if (!$this->isValidToken((string) $validated['reset_token'])) {
            return redirect()
                ->route('system.reset-mvp-data.index')
                ->withErrors(['reset_token' => '確認トークンが切れている。もう一度、件数確認からやり直して。']);
        }

        $beforeCounts = $this->buildCounts();
        $beforeTotal = $this->totalCount($beforeCounts);

        $this->resetOperationalTables();
        session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('system.reset-mvp-data.index')
            ->with('status', 'MVPデータをリセットした。削除対象件数：'.number_format($beforeTotal).'件。users / migrations / マスタ / 業界スコア設定は残している。');
    }

    /**
     * @return array<int, array{table: string, label: string, note: string, exists: bool, count: int|null, error: string|null}>
     */
    private function buildCounts(): array
    {
        $rows = [];

        foreach ($this->resetTableDefinitions() as $table => $meta) {
            $exists = Schema::hasTable($table);
            $count = null;
            $error = null;

            if ($exists) {
                try {
                    $count = DB::table($table)->count();
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }

            $rows[] = [
                'table' => $table,
                'label' => $meta['label'],
                'note' => $meta['note'],
                'exists' => $exists,
                'count' => $count,
                'error' => $error,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array{count: int|null}> $counts
     */
    private function totalCount(array $counts): int
    {
        return array_sum(array_map(
            static fn (array $row): int => is_int($row['count']) ? $row['count'] : 0,
            $counts
        ));
    }

    private function isValidToken(string $token): bool
    {
        $payload = session(self::SESSION_KEY);

        if (!is_array($payload)) {
            return false;
        }

        if (!hash_equals((string) ($payload['token'] ?? ''), $token)) {
            return false;
        }

        $createdAt = (int) ($payload['created_at'] ?? 0);

        return $createdAt > 0 && (now()->timestamp - $createdAt) <= 1800;
    }

    private function resetOperationalTables(): void
    {
        $driver = DB::getDriverName();
        $tables = array_keys($this->resetTableDefinitions());

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                if ($driver === 'pgsql') {
                    DB::statement('TRUNCATE TABLE '.$table.' RESTART IDENTITY CASCADE');
                    continue;
                }

                if ($driver === 'sqlite') {
                    DB::table($table)->delete();
                    try {
                        DB::statement("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
                    } catch (Throwable) {
                        // sqlite_sequence may not exist when the table has no autoincrement key.
                    }
                    continue;
                }

                DB::table($table)->truncate();
            }
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }
}
