<?php
$appDir = '/var/www/trusteps-cms-lab';

// ---- 1. ナビパッチ ----
$navFile = $appDir . '/resources/views/layouts/app.blade.php';
$content = file_get_contents($navFile);

$old = "                <a class=\"nav-link {{ request()->routeIs('discovery.*') ? 'active' : '' }}\" href=\"{{ route('discovery.lab') }}\">
                    候補収集ラボ
                </a>
                <a class=\"nav-link {{ request()->routeIs('directory-sources.index') || request()->routeIs('directory-sources.show') ? 'active' : '' }}\" href=\"{{ route('directory-sources.index') }}\">
                    名簿元管理
                </a>
                <a class=\"nav-link {{ request()->routeIs('directory-sources.lab') || request()->routeIs('directory-sources.lab.*') ? 'active' : '' }}\" href=\"{{ route('directory-sources.lab') }}\">
                    名簿元収集
                </a>
                <a class=\"nav-link {{ request()->routeIs('directory-sources.shokokai-bulk-html*') ? 'active' : '' }}\" href=\"{{ route('directory-sources.shokokai-bulk-html') }}\">
                    商工会HTML取込
                </a>
                <a class=\"nav-link {{ request()->routeIs('directory-sources.shokokai-web-search*') ? 'active' : '' }}\" href=\"{{ route('directory-sources.shokokai-web-search') }}\">
                    商工会WEBサーチ
                </a>
                <a class=\"nav-link {{ request()->routeIs('resolver.official-sites.*') ? 'active' : '' }}\" href=\"{{ route('resolver.official-sites.index') }}\">
                    公式HP取得
                </a>";

$new = "                <a class=\"nav-link {{ request()->routeIs('bizmaps.*') ? 'active' : '' }}\" href=\"{{ route('bizmaps.import') }}\">
                    BIZMAPSインポート
                </a>";

if (strpos($content, $old) !== false) {
    file_put_contents($navFile, str_replace($old, $new, $content));
    echo "[post_deploy] Nav patched OK\n";
} elseif (strpos($content, 'bizmaps.import') !== false) {
    echo "[post_deploy] Nav already patched. Skip.\n";
} else {
    echo "[post_deploy] WARNING: Nav pattern not found.\n";
}

// ---- 2. ルートパッチ ----
$routesFile = $appDir . '/routes/web.php';
$routesContent = file_get_contents($routesFile);

$routeOld = "    Route::post('/bizmaps/store', [BizmapsImportController::class, 'store'])->name('bizmaps.store');";
$routeNew = "    Route::post('/bizmaps/store', [BizmapsImportController::class, 'store'])->name('bizmaps.store');
    Route::get('/bizmaps/fetch-hp-stream', [BizmapsImportController::class, 'fetchHpStream'])->name('bizmaps.fetch-hp-stream');";

if (strpos($routesContent, 'fetch-hp-stream') !== false) {
    echo "[post_deploy] Routes already patched. Skip.\n";
} elseif (strpos($routesContent, $routeOld) !== false) {
    file_put_contents($routesFile, str_replace($routeOld, $routeNew, $routesContent));
    echo "[post_deploy] Routes patched OK\n";
} else {
    echo "[post_deploy] WARNING: Routes pattern not found.\n";
}

echo "[post_deploy] Done.\n";
