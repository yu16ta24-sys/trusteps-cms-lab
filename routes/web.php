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
    Route::post('/source-records/import', [SourceRecordController::class, 'import'])->name('source-records.import.store');

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
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
});
