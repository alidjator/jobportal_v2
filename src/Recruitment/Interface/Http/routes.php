<?php

use Illuminate\Support\Facades\Route;
use Src\Recruitment\Interface\Http\Controllers\CompanyStageController;

// The stages of the caller's own company; every use case checks that the caller is a member of an active company (and a company admin for changes).
Route::middleware('auth:user')->group(function (): void {
    Route::get('company/stages', [CompanyStageController::class, 'index']);
    Route::post('company/stages', [CompanyStageController::class, 'store']);
    Route::put('company/stages/order', [CompanyStageController::class, 'reorder']);
    Route::patch('company/stages/{id}', [CompanyStageController::class, 'update'])->whereNumber('id');
    Route::delete('company/stages/{id}', [CompanyStageController::class, 'destroy'])->whereNumber('id');
});
