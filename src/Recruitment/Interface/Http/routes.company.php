<?php

use Illuminate\Support\Facades\Route;
use Src\Recruitment\Interface\Http\Controllers\JobManagement\CompanyJobController;

// Sisi perusahaan: lowongan & lamaran (prefix `api` dari ContextServiceProvider).
Route::middleware(['auth:sanctum', 'api_company'])->prefix('company')->controller(CompanyJobController::class)->group(function () {
    Route::get('/create/job', 'create');
    Route::post('/store/job', 'store');
    Route::get('/edit/{slug}/job', 'edit');
    Route::put('/update/{slug}/job', 'update');
    Route::post('/promote/job', 'promote');
    Route::post('/clone/job', 'replicate');
    Route::post('/change-status/job', 'changeStatus');

    Route::prefix('job')->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}/applications', 'applications')->whereNumber('id');
        Route::post('/applications/{id}/group-update', 'groupUpdate')->whereNumber('id');
        Route::get('/download-cv/{id}', 'downloadCv')->whereNumber('id');
    });
});
