<?php

use Illuminate\Support\Facades\Route;
use Src\Company\Interface\Http\Controllers\CompanyController;
use Src\Company\Interface\Http\Controllers\CompanyDirectoryController;

Route::controller(CompanyDirectoryController::class)->group(function () {
    Route::get('/companies', 'index');
    Route::get('/companies/{username}', 'show');
});

Route::middleware(['auth:sanctum', 'api_company'])->prefix('company')->controller(CompanyController::class)->group(function () {
    Route::get('/', 'company');
    Route::get('/dashboard', 'dashboard');
    Route::post('/bookmark/candidate', 'bookmarkCandidate');
    Route::get('/bookmark/candidates', 'bookmarkedCandidates');
    Route::get('/bookmark/categories', 'bookmarkCategories');
    Route::post('/bookmark/categories', 'storeBookmarkCategory');
    Route::get('/bookmark/categories/{category}/edit', 'editBookmarkCategory')->whereNumber('category');
    Route::put('/bookmark/categories/{category}', 'updateBookmarkCategory')->whereNumber('category');
    Route::delete('/bookmark/categories/{category}', 'deleteBookmarkCategory')->whereNumber('category');
    Route::get('/application/group', 'applicationGroups');
    Route::post('/application/group', 'storeApplicationGroup');
    Route::put('/application/group/{group}', 'updateApplicationGroup')->whereNumber('group');
    Route::delete('/application/group/{group}', 'deleteApplicationGroup')->whereNumber('group');
    Route::get('/account-progress', 'accountProgress');
    Route::post('/account-progress', 'submitAccountProgress');
    Route::get('/social-links', 'socialLinks');
});
