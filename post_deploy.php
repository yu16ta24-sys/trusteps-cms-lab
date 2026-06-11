<?php
$appDir = '/var/www/trusteps-cms-lab';

// ---- 1. @stack('scripts') 復元 ----
$navFile = $appDir . '/resources/views/layouts/app.blade.php';
$content = file_get_contents($navFile);

if (strpos($content, "@stack('scripts')") === false) {
    $content = str_replace('</body>', "@stack('scripts')\n</body>", $content);
    file_put_contents($navFile, $content);
    echo "[post_deploy] @stack('scripts') restored OK\n";
} else {
    echo "[post_deploy] @stack('scripts') already exists. Skip.\n";
}

echo "[post_deploy] Done.\n";
