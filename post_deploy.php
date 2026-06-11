<?php
/**
 * post_deploy.php - v0.21.13
 * 1. routes/web.php に companies.edit / companies.update ルート追加
 * 2. CompanyController.php に edit / update メソッド追加
 */

$base = '/var/www/trusteps-cms-lab';

// ====== 1. routes/web.php ======
$routesFile = $base . '/routes/web.php';
$routesContent = file_get_contents($routesFile);

$oldRoute = "    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');";
$newRoute = "    Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');";

if (strpos($routesContent, 'companies.edit') !== false) {
    echo "[routes] companies.edit already exists, skip\n";
} elseif (strpos($routesContent, $oldRoute) === false) {
    echo "[routes] old route pattern not found, SKIP\n";
} else {
    file_put_contents($routesFile, str_replace($oldRoute, $newRoute, $routesContent));
    echo "[routes] companies.edit/update routes added OK\n";
}

// ====== 2. CompanyController.php ======
$controllerFile = $base . '/app/Http/Controllers/CompanyController.php';
$controllerContent = file_get_contents($controllerFile);

$insertBefore = '    private function isDirectorySourceRecord(SourceRecord $sourceRecord): bool';

$newMethods = '    public function edit(Company $company): View|RedirectResponse
    {
        if ($company->status === \'merged\') {
            return redirect()
                ->route(\'companies.show\', $company)
                ->with(\'status\', \'統合済みcompanyは編集できない。\');
        }

        $industries = Industry::query()
            ->where(\'is_active\', true)
            ->orderBy(\'sort_order\')
            ->orderBy(\'id\')
            ->get();

        $municipalities = Municipality::query()
            ->with(\'prefecture\')
            ->orderBy(\'code\')
            ->get();

        return view(\'companies.edit\', compact(\'company\', \'industries\', \'municipalities\'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        if ($company->status === \'merged\') {
            return redirect()
                ->route(\'companies.show\', $company)
                ->with(\'status\', \'統合済みcompanyは編集できない。\');
        }

        $validated = $request->validate([
            \'display_name\'     => [\'required\', \'string\', \'max:255\'],
            \'legal_name\'       => [\'nullable\', \'string\', \'max:255\'],
            \'corporate_number\' => [\'nullable\', \'string\', \'max:13\'],
            \'status\'           => [\'required\', \'in:candidate,confirmed\'],
            \'industry_id\'      => [\'nullable\', \'exists:industries,id\'],
            \'municipality_id\'  => [\'nullable\', \'exists:municipalities,id\'],
            \'pref\'             => [\'nullable\', \'string\', \'max:50\'],
            \'city\'             => [\'nullable\', \'string\', \'max:100\'],
            \'primary_url\'      => [\'nullable\', \'string\', \'max:2000\'],
        ]);

        $company->update([
            \'display_name\'     => $validated[\'display_name\'],
            \'legal_name\'       => $validated[\'legal_name\'] ?? null,
            \'name_norm\'        => $this->normalizeName($validated[\'display_name\']),
            \'corporate_number\' => $this->normalizeCorporateNumber($validated[\'corporate_number\'] ?? null),
            \'status\'           => $validated[\'status\'],
            \'industry_id\'      => $validated[\'industry_id\'] ?? null,
            \'municipality_id\'  => $validated[\'municipality_id\'] ?? null,
            \'pref\'             => !empty($validated[\'municipality_id\']) ? null : ($validated[\'pref\'] ?? null),
            \'city\'             => !empty($validated[\'municipality_id\']) ? null : ($validated[\'city\'] ?? null),
        ]);

        $newUrl = trim((string) ($validated[\'primary_url\'] ?? \'\'));
        $currentUrl = $company->primaryDomain?->url ?? \'\';

        if ($newUrl !== \'\' && $newUrl !== $currentUrl) {
            $domain = Domain::create([
                \'company_id\'        => $company->id,
                \'url\'               => $newUrl,
                \'normalized_domain\' => $this->normalizeDomain($newUrl),
                \'role\'              => \'official\',
                \'is_primary\'        => true,
                \'is_portal\'         => false,
            ]);

            if ($company->primary_domain_id) {
                Domain::where(\'id\', $company->primary_domain_id)->update([\'is_primary\' => false]);
            }

            $company->update([\'primary_domain_id\' => $domain->id]);
        }

        return redirect()
            ->route(\'companies.show\', $company)
            ->with(\'status\', \'company情報を更新した。\');
    }

';

if (strpos($controllerContent, 'public function edit(Company $company)') !== false) {
    echo "[controller] edit() already exists, skip\n";
} elseif (strpos($controllerContent, $insertBefore) === false) {
    echo "[controller] insert position not found, SKIP\n";
} else {
    $newControllerContent = str_replace($insertBefore, $newMethods . $insertBefore, $controllerContent);
    file_put_contents($controllerFile, $newControllerContent);
    echo "[controller] edit/update methods added OK\n";
}

$lintResult = shell_exec("php -l {$controllerFile} 2>&1");
echo "[lint] " . trim($lintResult) . "\n";

echo "\n[post_deploy] Done.\n";
