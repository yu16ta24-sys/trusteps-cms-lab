<?php
/**
 * post_deploy.php - v0.21.15
 * CompanyController::analyze() を自動スコア保存版に差し替える
 */

$base = '/var/www/trusteps-cms-lab';
$file = $base . '/app/Http/Controllers/CompanyController.php';
$content = file_get_contents($file);

$old = '    public function analyze(Request $request, Company $company): RedirectResponse
    {
        if (!$company->primaryDomain) {
            return redirect()
                ->route(\'companies.show\', $company)
                ->with(\'status\', \'primary_domainが未設定のため解析できません。\');
        }
        try {
            $analyzer = app(HpAnalyzerService::class);
            $result = $analyzer->analyze($company);
            if ($result[\'success\']) {
                return redirect()
                    ->route(\'companies.show\', $company)
                    ->with(\'status\', \'HP解析完了。スコア自動提案を更新しました。\');
            }
            return redirect()
                ->route(\'companies.show\', $company)
                ->with(\'status\', \'HP解析失敗：\' . $result[\'message\']);
        } catch (\Throwable $e) {
            report($e);
            return redirect()
                ->route(\'companies.show\', $company)
                ->with(\'status\', \'HP解析中にエラーが発生しました。\');
        }
    }';

$new = '    public function analyze(Request $request, Company $company): RedirectResponse
    {
        if (!$company->primaryDomain) {
            return redirect()
                ->route(\'companies.show\', $company)
                ->with(\'status\', \'primary_domainが未設定のため解析できません。\');
        }
        try {
            $analyzer = app(HpAnalyzerService::class);
            $result   = $analyzer->analyze($company);

            if (!$result[\'success\']) {
                return redirect()
                    ->route(\'companies.show\', $company)
                    ->with(\'status\', \'HP解析失敗：\' . $result[\'message\']);
            }

            // 解析完了後、スコア自動提案を取得して即座に保存
            $company->load([\'industry\', \'domains\', \'primaryDomain\', \'scores\']);
            $suggestions = app(\App\Services\ScoreSuggester::class)->suggest($company);
            $axisKeys    = array_keys($this->scoreAxisOptions());
            $savedCount  = 0;

            foreach ($axisKeys as $axis) {
                $suggestion = $suggestions[$axis] ?? null;
                if (!$suggestion || $suggestion[\'value\'] === null) {
                    continue;
                }
                $value      = (int) $suggestion[\'value\'];
                $confidence = in_array($suggestion[\'confidence\'], [\'0.3\', \'0.6\', \'0.9\'])
                    ? $suggestion[\'confidence\'] : \'0.3\';
                $reasonJson = [
                    \'basis\'   => \'hp_analysis_auto\',
                    \'drivers\' => $suggestion[\'drivers\'] ?? [],
                    \'note\'    => $suggestion[\'note\'] ?? null,
                    \'auto_suggestion\' => [
                        \'algo_version\' => \App\Services\ScoreSuggester::ALGO,
                        \'value\'        => $value,
                        \'confidence\'   => $confidence,
                        \'basis\'        => $suggestion[\'basis\'] ?? \'auto\',
                        \'drivers\'      => $suggestion[\'drivers\'] ?? [],
                        \'note\'         => $suggestion[\'note\'] ?? null,
                    ],
                ];
                CompanyScore::updateOrCreate(
                    [\'company_id\' => $company->id, \'axis\' => $axis, \'algo_version\' => \'v1\'],
                    [
                        \'value\'                => $value,
                        \'confidence\'           => $confidence,
                        \'auto_suggested_value\'  => $value,
                        \'reason_json\'          => $reasonJson,
                        \'scored_by\'            => \'hp_analysis_auto\',
                        \'scored_at\'            => now(),
                    ]
                );
                $savedCount++;
            }

            return redirect()
                ->route(\'companies.show\', $company)
                ->with(\'status\', "HP解析完了。{$savedCount}軸のスコアを自動保存しました。");

        } catch (\Throwable $e) {
            report($e);
            return redirect()
                ->route(\'companies.show\', $company)
                ->with(\'status\', \'HP解析中にエラーが発生しました。\');
        }
    }';

if (strpos($content, $old) === false) {
    echo "[controller] old pattern not found - already updated or mismatch\n";
    echo "[controller] checking if new version already exists...\n";
    if (strpos($content, 'hp_analysis_auto') !== false) {
        echo "[controller] already updated to auto-save version, skip\n";
    } else {
        echo "[controller] SKIP - manual check required\n";
    }
} else {
    file_put_contents($file, str_replace($old, $new, $content));
    echo "[controller] analyze() updated to auto-save version OK\n";
}

$lint = shell_exec('php -l ' . $file . ' 2>&1');
echo '[lint] ' . trim($lint) . "\n";
echo "\n[post_deploy] Done.\n";
