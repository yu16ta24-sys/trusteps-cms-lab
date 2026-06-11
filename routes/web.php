<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscoveryLabController;
use App\Http\Controllers\DirectorySourceLabController;
use App\Http\Controllers\DirectorySourceController;
use App\Http\Controllers\IndustryScoreController;
use App\Http\Controllers\MvpResetController;
use App\Http\Controllers\OfficialSiteResolverController;
use App\Http\Controllers\SourceRecordController;
use App\Http\Controllers\ShokokaiBulkHtmlImportController;
use App\Http\Controllers\ShokokaiWebSearchController;
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




    Route::get('/directory-sources/shokokai-bulk-html', [ShokokaiBulkHtmlImportController::class, 'show'])->name('directory-sources.shokokai-bulk-html');
    Route::post('/directory-sources/shokokai-bulk-html/preview', [ShokokaiBulkHtmlImportController::class, 'preview'])->name('directory-sources.shokokai-bulk-html.preview');
    Route::post('/directory-sources/shokokai-bulk-html/store', [ShokokaiBulkHtmlImportController::class, 'store'])->name('directory-sources.shokokai-bulk-html.store');
    Route::get('/directory-sources/shokokai-bulk-html/preview', fn () => redirect()->route('directory-sources.shokokai-bulk-html'));
    Route::get('/directory-sources/shokokai-bulk-html/store', fn () => redirect()->route('directory-sources.shokokai-bulk-html'));

    Route::get('/directory-sources/shokokai-web-search', [ShokokaiWebSearchController::class, 'show'])->name('directory-sources.shokokai-web-search');
    Route::post('/directory-sources/shokokai-web-search/preview', [ShokokaiWebSearchController::class, 'preview'])->name('directory-sources.shokokai-web-search.preview');
    Route::post('/directory-sources/shokokai-web-search/store', [ShokokaiWebSearchController::class, 'store'])->name('directory-sources.shokokai-web-search.store');
    Route::get('/directory-sources/shokokai-web-search/preview', fn () => redirect()->route('directory-sources.shokokai-web-search'));
    Route::get('/directory-sources/shokokai-web-search/store', fn () => redirect()->route('directory-sources.shokokai-web-search'));


    Route::get('/directory-sources', [DirectorySourceController::class, 'index'])->name('directory-sources.index');
    Route::post('/directory-sources/import-source-records', [DirectorySourceController::class, 'importFromSourceRecords'])->name('directory-sources.import-source-records');
    Route::post('/directory-sources/crawl-selected', [DirectorySourceController::class, 'crawlSelected'])->name('directory-sources.crawl-selected');
    Route::post('/directory-sources/crawl-queue', [DirectorySourceController::class, 'crawlQueue'])->name('directory-sources.crawl-queue');
    Route::post('/directory-sources/{directorySource}/crawl', [DirectorySourceController::class, 'crawlOne'])->whereNumber('directorySource')->name('directory-sources.crawl-one');
    Route::get('/directory-sources/{directorySource}', [DirectorySourceController::class, 'show'])->whereNumber('directorySource')->name('directory-sources.show');

    Route::get('/directory-sources/lab', [DirectorySourceLabController::class, 'show'])->name('directory-sources.lab');
    Route::post('/directory-sources/lab/preview', [DirectorySourceLabController::class, 'preview'])->name('directory-sources.lab.preview');
    Route::post('/directory-sources/lab/store', [DirectorySourceLabController::class, 'store'])->name('directory-sources.lab.store');
    Route::get('/directory-sources/lab/preview', fn () => redirect()->route('directory-sources.lab'));
    Route::get('/directory-sources/lab/store', fn () => redirect()->route('directory-sources.lab'));

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
    Route::get('/industries/scores/{industry}/edit', [IndustryScoreController::class, 'edit'])->name('industries.scores.edit');
    Route::put('/industries/scores/{industry}', [IndustryScoreController::class, 'update'])->name('industries.scores.update');

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
    Route::post('/source-records/cleanup-directory-source-companies', [SourceRecordController::class, 'cleanupDirectorySourceCompanies'])->name('source-records.cleanup-directory-source-companies');

    Route::get('/source-records/{sourceRecord}/create-company', [CompanyController::class, 'createFromSource'])
        ->name('companies.create-from-source');
    Route::post('/source-records/{sourceRecord}/create-company', [CompanyController::class, 'storeFromSource'])
        ->name('companies.store-from-source');

    Route::get('/source-records/{sourceRecord}/link-company', [CompanyController::class, 'linkExistingFromSource'])
        ->name('companies.link-existing-from-source');
    Route::post('/source-records/{sourceRecord}/link-company', [CompanyController::class, 'storeLinkExistingFromSource'])
        ->name('companies.store-link-existing-from-source');

    Route::get('/source-records/{sourceRecord}', [SourceRecordController::class, 'show'])->name('source-records.show');

    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/candidates', [CompanyController::class, 'candidates'])->name('companies.candidates');
    Route::get('/companies/{company}/merge', [CompanyController::class, 'mergeForm'])->name('companies.merge-form');
    Route::post('/companies/{company}/merge', [CompanyController::class, 'merge'])->name('companies.merge');
    Route::post('/companies/{company}/undo-merge', [CompanyController::class, 'undoMerge'])->name('companies.undo-merge');
    Route::post('/companies/{company}/scores', [CompanyController::class, 'storeScores'])->name('companies.scores.store');
    Route::post('/companies/{company}/kill-flags', [CompanyController::class, 'storeKillFlag'])->name('companies.kill-flags.store');
    Route::delete('/companies/{company}/kill-flags/{killFlag}', [CompanyController::class, 'deleteKillFlag'])->name('companies.kill-flags.destroy');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
});
