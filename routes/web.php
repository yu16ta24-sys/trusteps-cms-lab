<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BizmapsImportController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscoveryLabController;
use App\Http\Controllers\IndustryScoreController;
use App\Http\Controllers\MvpResetController;
use App\Http\Controllers\OfficialSiteResolverController;
use App\Http\Controllers\OutreachController;
use App\Http\Controllers\SourceRecordController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/resolver/official-sites', [OfficialSiteResolverController::class, 'show'])->name('resolver.official-sites.index');
    Route::post('/resolver/official-sites/preview', [OfficialSiteResolverController::class, 'preview'])->name('resolver.official-sites.preview');
    Route::post('/resolver/official-sites/store', [OfficialSiteResolverController::class, 'store'])->name('resolver.official-sites.store');
    Route::get('/resolver/official-sites/preview', fn () => redirect()->route('resolver.official-sites.index'));
    Route::get('/resolver/official-sites/store', fn () => redirect()->route('resolver.official-sites.index'));

    Route::get('/discovery/lab', [DiscoveryLabController::class, 'show'])->name('discovery.lab');
    Route::post('/discovery/lab/preview', [DiscoveryLabController::class, 'preview'])->name('discovery.lab.preview');
    Route::post('/discovery/lab/directory-preview', [DiscoveryLabController::class, 'directoryPreview'])->name('discovery.lab.directory-preview');
    Route::get('/discovery/lab/directory-preview', fn () => redirect()->route('discovery.lab'));
    Route::get('/discovery/lab/preview', fn () => redirect()->route('discovery.lab'));
    Route::get('/discovery/lab/store', fn () => redirect()->route('discovery.lab'));
    Route::get('/discovery/lab/export-csv', fn () => redirect()->route('discovery.lab'));
    Route::post('/discovery/lab/store', [DiscoveryLabController::class, 'store'])->name('discovery.lab.store');
    Route::post('/discovery/lab/export-csv', [DiscoveryLabController::class, 'exportCsv'])->name('discovery.lab.export-csv');


    Route::get('/industries/scores', [IndustryScoreController::class, 'index'])->name('industries.scores.index');
    Route::get('/industries/scores/export', [IndustryScoreController::class, 'export'])->name('industries.scores.export');
    Route::get('/industries/scores/import', [IndustryScoreController::class, 'importForm'])->name('industries.scores.import');
    Route::post('/industries/scores/import/preview', [IndustryScoreController::class, 'importPreview'])->name('industries.scores.import.preview');
    Route::post('/industries/scores/import/store', [IndustryScoreController::class, 'importStore'])->name('industries.scores.import.store');
    Route::get('/industries/scores/{industry}/edit', [IndustryScoreController::class, 'edit'])->name('industries.scores.edit');
    Route::put('/industries/scores/{industry}', [IndustryScoreController::class, 'update'])->name('industries.scores.update');
    Route::post('/industries/scores/bulk-update/{parent}', [IndustryScoreController::class, 'bulkUpdateByParent'])->name('industries.scores.bulk-update');

    Route::get('/system/reset-mvp-data', [MvpResetController::class, 'show'])->name('system.reset-mvp-data.index');
    Route::post('/system/reset-mvp-data/preview', [MvpResetController::class, 'preview'])->name('system.reset-mvp-data.preview');
    Route::post('/system/reset-mvp-data/confirm', [MvpResetController::class, 'confirm'])->name('system.reset-mvp-data.confirm');
    Route::post('/system/reset-mvp-data/destroy', [MvpResetController::class, 'destroy'])->name('system.reset-mvp-data.destroy');

    Route::get('/source-records', [SourceRecordController::class, 'index'])->name('source-records.index');
    Route::get('/source-records/create', [SourceRecordController::class, 'create'])->name('source-records.create');
    Route::post('/source-records', [SourceRecordController::class, 'store'])->name('source-records.store');
    Route::get('/source-records/import', [SourceRecordController::class, 'importForm'])->name('source-records.import');
    Route::get('/source-records/import/template', [SourceRecordController::class, 'importTemplate'])->name('source-records.import.template');
    Route::post('/source-records/import', [SourceRecordController::class, 'import'])->name('source-records.import.store');
    Route::post('/source-records/import/confirm', [SourceRecordController::class, 'confirmImport'])->name('source-records.import.confirm');
    Route::post('/source-records/import/cancel', [SourceRecordController::class, 'cancelImport'])->name('source-records.import.cancel');
    Route::post('/source-records/bulk-create-companies', [SourceRecordController::class, 'bulkCreateCompanies'])->name('source-records.bulk-create-companies');
    Route::post('/source-records/bulk-kill', [SourceRecordController::class, 'bulkKill'])->name('source-records.bulk-kill');

    Route::get('/source-records/{sourceRecord}/create-company', [CompanyController::class, 'createFromSource'])
        ->name('companies.create-from-source');
    Route::post('/source-records/{sourceRecord}/create-company', [CompanyController::class, 'storeFromSource'])
        ->name('companies.store-from-source');

    Route::get('/source-records/{sourceRecord}', [SourceRecordController::class, 'show'])->name('source-records.show');

    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/candidates', [CompanyController::class, 'candidates'])->name('companies.candidates');
    Route::get('/companies/analyze-unanalyzed/stream', [CompanyController::class, 'analyzeUnanalyzedStream'])->name('companies.analyze-unanalyzed.stream');
    Route::get('/companies/{company}/merge', [CompanyController::class, 'mergeForm'])->name('companies.merge-form');
    Route::post('/companies/{company}/merge', [CompanyController::class, 'merge'])->name('companies.merge');
    Route::post('/companies/{company}/undo-merge', [CompanyController::class, 'undoMerge'])->name('companies.undo-merge');
    Route::post('/companies/{company}/kill-flags', [CompanyController::class, 'storeKillFlag'])->name('companies.kill-flags.store');
    Route::delete('/companies/{company}/kill-flags/{killFlag}', [CompanyController::class, 'deleteKillFlag'])->name('companies.kill-flags.destroy');
    Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::post('/companies/{company}/analyze', [CompanyController::class, 'analyze'])->name('companies.analyze');
    Route::post('/companies/{company}/set-primary-url', [CompanyController::class, 'setPrimaryUrl'])->name('companies.set-primary-url');
    Route::post('/companies/{company}/manual-candidate', [CompanyController::class, 'setManualCandidate'])->name('companies.manual-candidate.set');
    Route::delete('/companies/{company}/manual-candidate', [CompanyController::class, 'unsetManualCandidate'])->name('companies.manual-candidate.unset');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');

    // 営業管理
    Route::get('/outreach', [OutreachController::class, 'index'])->name('outreach.index');
    Route::post('/outreach/{company}/phase', [OutreachController::class, 'updatePhase'])->name('outreach.phase');
    Route::post('/outreach/{company}/contact', [OutreachController::class, 'storeContact'])->name('outreach.contact.store');
    Route::delete('/outreach/{company}/contact/{contact}', [OutreachController::class, 'destroyContact'])->name('outreach.contact.destroy');

    // BIZMAPSインポート
    Route::get('/bizmaps/import', [BizmapsImportController::class, 'index'])->name('bizmaps.import');
    Route::get('/bizmaps/municipalities', [BizmapsImportController::class, 'getMunicipalities'])->name('bizmaps.municipalities');
    Route::get('/bizmaps/sub-industries', [BizmapsImportController::class, 'getSubIndustries'])->name('bizmaps.sub-industries');
    Route::post('/bizmaps/preview', [BizmapsImportController::class, 'preview'])->name('bizmaps.preview');
    Route::get('/bizmaps/preview', fn () => redirect()->route('bizmaps.import'));
    Route::post('/bizmaps/store', [BizmapsImportController::class, 'store'])->name('bizmaps.store');
    Route::post('/bizmaps/store-companies', [BizmapsImportController::class, 'storeCompanies'])->name('bizmaps.store-companies');
    Route::post('/bizmaps/store-with-exclusion', [BizmapsImportController::class, 'storeWithExclusion'])->name('bizmaps.store-with-exclusion');
    Route::post('/bizmaps/store-with-exclusion-all', [BizmapsImportController::class, 'storeWithExclusionAll'])->name('bizmaps.store-with-exclusion-all');
    Route::get('/bizmaps/fetch-hp-stream', [BizmapsImportController::class, 'fetchHpStream'])->name('bizmaps.fetch-hp-stream');
    Route::post('/bizmaps/exclude', [BizmapsImportController::class, 'exclude'])->name('bizmaps.exclude');
    Route::post('/bizmaps/unexclude', [BizmapsImportController::class, 'unexclude'])->name('bizmaps.unexclude');
});
