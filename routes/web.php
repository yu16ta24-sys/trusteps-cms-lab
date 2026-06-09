<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
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

    Route::get('/source-records', [SourceRecordController::class, 'index'])->name('source-records.index');
    Route::get('/source-records/create', [SourceRecordController::class, 'create'])->name('source-records.create');
    Route::post('/source-records', [SourceRecordController::class, 'store'])->name('source-records.store');
    Route::get('/source-records/import', [SourceRecordController::class, 'importForm'])->name('source-records.import');
    Route::get('/source-records/import/template', [SourceRecordController::class, 'importTemplate'])->name('source-records.import.template');
    Route::post('/source-records/import', [SourceRecordController::class, 'import'])->name('source-records.import.store');
    Route::post('/source-records/import/confirm', [SourceRecordController::class, 'confirmImport'])->name('source-records.import.confirm');
    Route::post('/source-records/import/cancel', [SourceRecordController::class, 'cancelImport'])->name('source-records.import.cancel');
    Route::get('/source-records/next-unlinked', [SourceRecordController::class, 'nextUnlinked'])->name('source-records.next-unlinked');
    Route::post('/source-records/bulk-create-companies', [SourceRecordController::class, 'bulkCreateCompanies'])->name('source-records.bulk-create-companies');

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
