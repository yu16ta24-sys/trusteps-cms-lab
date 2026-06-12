<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyScore;
use App\Models\CompanyScoreSummary;
use App\Services\ScoreSuggester;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateScores extends Command
{
    protected $signature = 'scores:recalculate
        {--company_id= : 対象 company の ID（単体）}
        {--all : 全 company を対象にする}
        {--version=scoring_v1.0 : 保存する score_version}';

    protected $description = 'suggestV2() で 5軸スコアを再計算し company_scores / company_score_summaries に保存する';

    public function handle(ScoreSuggester $suggester): int
    {
        $version   = (string) $this->option('version');
        $companyId = $this->option('company_id');
        $all       = (bool) $this->option('all');

        if (!$all && !$companyId) {
            $this->error('--company_id=<id> か --all のいずれかを指定してください。');
            return self::FAILURE;
        }

        $query = Company::query()
            ->with(['industry.parent', 'killFlags', 'primaryDomain']);

        if ($companyId) {
            $query->where('id', (int) $companyId);
        }

        $total     = (clone $query)->count();
        if ($total === 0) {
            $this->warn('対象 company が見つかりませんでした。');
            return self::SUCCESS;
        }

        $this->info("再計算開始: {$total} 社 / score_version={$version}");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $failed    = 0;

        $query->chunkById(100, function ($companies) use ($suggester, $version, $bar, &$processed, &$failed) {
            foreach ($companies as $company) {
                try {
                    $result = $suggester->suggestV2($company);
                    $this->persist($company, $result, $version);
                    $processed++;

                    if ($this->output->isVerbose()) {
                        $bar->clear();
                        $this->line(sprintf(
                            '  #%d %s → total=%.2f rank=%s type=%s conf=%.2f',
                            $company->id,
                            $company->display_name ?? $company->legal_name ?? '(no name)',
                            $result['total_score'],
                            $result['rank'],
                            $result['candidate_type'],
                            $result['confidence']
                        ));
                        $bar->display();
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $bar->clear();
                    $this->error(sprintf('  #%d 失敗: %s', $company->id, $e->getMessage()));
                    $bar->display();
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("完了: 成功 {$processed} 件 / 失敗 {$failed} 件");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * 5軸スコアと summary を保存する。
     */
    private function persist(Company $company, array $result, string $version): void
    {
        DB::transaction(function () use ($company, $result, $version) {
            foreach ($result['axes'] as $axisKey => $axis) {
                CompanyScore::updateOrCreate(
                    [
                        'company_id'    => $company->id,
                        'axis'          => $axisKey,
                        'score_version' => $version,
                    ],
                    [
                        // company_scores.value は整数のため round。生の値は reason_json に保持。
                        'value'                => (int) round($axis['score']),
                        'auto_suggested_value' => (int) round($axis['score']),
                        'confidence'           => round((float) $axis['confidence'], 1),
                        'algo_version'         => ScoreSuggester::ALGO,
                        'reason_json'          => array_merge(
                            ['score_raw' => round((float) $axis['score'], 2)],
                            $axis['reason_json'] ?? []
                        ),
                        'scored_by'            => 'system:recalculate',
                        'scored_at'            => now(),
                    ]
                );
            }

            CompanyScoreSummary::updateOrCreate(
                [
                    'company_id'    => $company->id,
                    'score_version' => $version,
                ],
                [
                    'total_score'       => round((float) $result['total_score'], 2),
                    'rank'              => $result['rank'],
                    'candidate_type'    => $result['candidate_type'],
                    'confidence'        => round((float) $result['confidence'], 2),
                    'flags_json'        => $result['flags'],
                    'caps_applied_json' => $result['caps_applied'],
                    'reason_summary'    => $result['reason_summary'],
                ]
            );
        });
    }
}
