<?php
/**
 * post_deploy.php - v0.21.11
 * routes/web.php に bulk-update ルートを追加
 */

$base = '/var/www/trusteps-cms-lab';
$routesFile = $base . '/routes/web.php';
$content = file_get_contents($routesFile);

$oldRoute = "    Route::put('/industries/scores/{industry}', [IndustryScoreController::class, 'update'])->name('industries.scores.update');";
$newRoute = "    Route::put('/industries/scores/{industry}', [IndustryScoreController::class, 'update'])->name('industries.scores.update');
    Route::post('/industries/scores/bulk-update/{parent}', [IndustryScoreController::class, 'bulkUpdateByParent'])->name('industries.scores.bulk-update');";

if (strpos($content, 'bulk-update') !== false) {
    echo "[routes] bulk-update route already exists, skip\n";
} elseif (strpos($content, $oldRoute) === false) {
    echo "[routes] old route pattern not found, SKIP\n";
} else {
    file_put_contents($routesFile, str_replace($oldRoute, $newRoute, $content));
    echo "[routes] bulk-update route added OK\n";
}

echo "\n[post_deploy] Done.\n";
