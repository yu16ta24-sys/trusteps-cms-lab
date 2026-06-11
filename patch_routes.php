<?php
$file = '/var/www/trusteps-cms-lab/routes/web.php';
$content = file_get_contents($file);

$old = "    Route::post('/bizmaps/store', [BizmapsImportController::class, 'store'])->name('bizmaps.store');";
$new = "    Route::post('/bizmaps/store', [BizmapsImportController::class, 'store'])->name('bizmaps.store');
    Route::get('/bizmaps/fetch-hp-stream', [BizmapsImportController::class, 'fetchHpStream'])->name('bizmaps.fetch-hp-stream');";

if (strpos($content, $old) === false) {
    echo "ERROR: Pattern not found.\n";
    exit(1);
}

if (strpos($content, 'fetch-hp-stream') !== false) {
    echo "ALREADY_EXISTS\n";
    exit(0);
}

$result = str_replace($old, $new, $content);
file_put_contents($file, $result);
echo "OK\n";
