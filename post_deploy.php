<?php
/**
 * post_deploy.php - v0.21.14
 * 1. routes/web.php に companies.analyze ルート追加（sedで追加）
 * 2. CompanyController.php に analyze メソッド追加
 * 3. HpFact モデルに新カラムを fillable 追加
 */

$base = '/var/www/trusteps-cms-lab';

// ====== 1. routes/web.php - sedコマンドで追加 ======
$routesFile = $base . '/routes/web.php';
$routesContent = file_get_contents($routesFile);

if (strpos($routesContent, 'companies.analyze') !== false) {
    echo "[routes] companies.analyze already exists, skip\n";
} else {
    // companies.show の行番号を取得してその前に挿入
    $lines = explode("\n", $routesContent);
    $insertLine = null;
    foreach ($lines as $i => $line) {
        if (strpos($line, "name('companies.show')") !== false) {
            $insertLine = $i;
            break;
        }
    }

    if ($insertLine === null) {
        echo "[routes] companies.show not found, SKIP\n";
    } else {
        $newRoute = "    Route::post('/companies/{company}/analyze', [CompanyController::class, 'analyze'])->name('companies.analyze');";
        array_splice($lines, $insertLine, 0, [$newRoute]);
        file_put_contents($routesFile, implode("\n", $lines));
        echo "[routes] companies.analyze route added OK\n";
    }
}

// ====== 2. CompanyController.php に analyze メソッド追加 ======
$controllerFile = $base . '/app/Http/Controllers/CompanyController.php';
$controllerContent = file_get_contents($controllerFile);

if (strpos($controllerContent, 'public function analyze(') !== false) {
    echo "[controller] analyze() already exists, skip\n";
} else {
    // use文にHpAnalyzerServiceを追加
    $oldUse = 'use App\Services\ScoreSuggester;';
    $newUse = 'use App\Services\HpAnalyzerService;
use App\Services\ScoreSuggester;';

    if (strpos($controllerContent, 'HpAnalyzerService') === false) {
        $controllerContent = str_replace($oldUse, $newUse, $controllerContent);
        echo "[controller] HpAnalyzerService use added OK\n";
    }

    // analyzeメソッドをisDirectorySourceRecordの直前に追加
    $insertBefore = '    private function isDirectorySourceRecord(SourceRecord $sourceRecord): bool';

    $analyzeMethod = '    public function analyze(Request $request, Company $company): RedirectResponse
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
            } else {
                return redirect()
                    ->route(\'companies.show\', $company)
                    ->with(\'status\', \'HP解析失敗：\' . $result[\'message\']);
            }
        } catch (\Throwable $e) {
            report($e);
            return redirect()
                ->route(\'companies.show\', $company)
                ->with(\'status\', \'HP解析中にエラーが発生しました。\');
        }
    }

';

    if (strpos($controllerContent, $insertBefore) === false) {
        echo "[controller] insert position not found, SKIP\n";
    } else {
        $controllerContent = str_replace($insertBefore, $analyzeMethod . $insertBefore, $controllerContent);
        file_put_contents($controllerFile, $controllerContent);
        echo "[controller] analyze() method added OK\n";
    }
}

// ====== 3. HpFact モデルに新カラムを fillable 追加 ======
$hpFactFile = $base . '/app/Models/HpFact.php';
$hpFactContent = file_get_contents($hpFactFile);

if (strpos($hpFactContent, 'hp_title') !== false) {
    echo "[model] HpFact already has hp_title, skip\n";
} else {
    $oldFillable = "        'extractor_version',
        'extracted_at',
    ];";
    $newFillable = "        'extractor_version',
        'extracted_at',
        'hp_title',
        'hp_description',
        'hp_last_modified',
        'hp_has_news',
        'hp_latest_post_date',
        'hp_update_staleness_days',
        'hp_page_count',
        'hp_has_map',
        'hp_image_count',
        'hp_word_count',
        'hp_has_tabelog',
        'hp_has_hotpepper',
        'hp_has_jalan',
        'hp_has_suumo',
        'hp_portal_links',
        'hp_improvement_score',
    ];";

    if (strpos($hpFactContent, $oldFillable) === false) {
        echo "[model] HpFact fillable pattern not found, SKIP\n";
    } else {
        file_put_contents($hpFactFile, str_replace($oldFillable, $newFillable, $hpFactContent));
        echo "[model] HpFact fillable updated OK\n";
    }
}

// ====== 4. php -l チェック ======
$lintCtrl = shell_exec("php -l {$controllerFile} 2>&1");
echo "[lint controller] " . trim($lintCtrl) . "\n";

$lintAnalyzer = shell_exec("php -l {$base}/app/Services/HpAnalyzerService.php 2>&1");
echo "[lint analyzer] " . trim($lintAnalyzer) . "\n";

$lintSuggester = shell_exec("php -l {$base}/app/Services/ScoreSuggester.php 2>&1");
echo "[lint suggester] " . trim($lintSuggester) . "\n";

echo "\n[post_deploy] Done.\n";
