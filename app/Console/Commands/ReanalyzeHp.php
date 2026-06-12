<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\HpAnalyzerService;
use Illuminate\Console\Command;

class ReanalyzeHp extends Command
{
    protected $signature = 'hp:reanalyze
        {--company_id= : 対象 company の ID（単体）}
        {--all : 全 company を対象にする（primary_domain 保有のみ）}
        {--skip-recalculate : 再解析後のスコア再計算をスキップする}';

    protected $description = 'HP を再取得・解析し hp_facts を更新する。--all で全社一括処理';

    public function handle(HpAnalyzerService $analyzer): int
    {
        $companyId = $this->option('company_id');
        $all       = (bool) $this->option('all');

        if (!$all && !$companyId) {
            $this->error('--company_id=<id> か --all のいずれかを指定してください。');
            return self::FAILURE;
        }

        $query = Company::query()
            ->with(['primaryDomain', 'municipality.prefecture'])
            ->whereHas('primaryDomain');

        if ($companyId) {
            $query->where('id', (int) $companyId);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->warn('primary_domain を持つ company が見つかりませんでした。');
            return self::SUCCESS;
        }

        $this->info("HP再解析開始: {$total} 社");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $ok      = 0;
        $skipped = 0;
        $failed  = 0;

        $query->chunkById(50, function ($companies) use ($analyzer, $bar, &$ok, &$skipped, &$failed) {
            foreach ($companies as $company) {
                try {
                    $result = $analyzer->analyze($company);

                    if (!$result['success']) {
                        $skipped++;
                        if ($this->output->isVerbose()) {
                            $bar->clear();
                            $this->line(sprintf(
                                '  #%d %s → SKIP: %s',
                                $company->id,
                                $company->display_name ?? $company->legal_name ?? '(no name)',
                                $result['message'] ?? ''
                            ));
                            $bar->display();
                        }
                        $bar->advance();
                        continue;
                    }

                    // http→https へ自動昇格
                    if (($result['url_upgraded'] ?? false) && !empty($result['https_url'])) {
                        $company->primaryDomain->update(['url' => $result['https_url']]);
                        if ($this->output->isVerbose()) {
                            $bar->clear();
                            $this->line(sprintf('  #%d URL→https自動更新: %s', $company->id, $result['https_url']));
                            $bar->display();
                        }
                    }

                    $ok++;
                    if ($this->output->isVerbose()) {
                        $bar->clear();
                        $this->line(sprintf(
                            '  #%d %s → OK (ssl=%s js=%s update=%s)',
                            $company->id,
                            $company->display_name ?? $company->legal_name ?? '(no name)',
                            ($result['ssl_enabled'] ?? false) ? 'yes' : 'no',
                            ($result['js_rendering_required'] ?? false) ? 'yes' : 'no',
                            $result['update_status'] ?? '-'
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
        $this->info("HP再解析完了: 成功 {$ok} / スキップ {$skipped} / 失敗 {$failed} 件");

        if (!$this->option('skip-recalculate')) {
            $this->info('スコア再計算を実行します...');
            $exitCode = $this->call('scores:recalculate', ['--all' => true]);
            return $exitCode;
        }

        return ($failed > 0) ? self::FAILURE : self::SUCCESS;
    }
}
