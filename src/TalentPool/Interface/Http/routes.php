<?php

use Illuminate\Support\Facades\Route;
use Src\TalentPool\Infrastructure\TalentPoolServiceProvider;
use Src\TalentPool\Interface\Http\Controllers\CategoryController;
use Src\TalentPool\Interface\Http\Controllers\TalentPoolController;

// The talent pool of the caller's company, shared by its members. Every use case checks that the caller is a member of an active company; renaming and
// deleting a category also needs a company admin.
Route::middleware('auth:user')->group(function (): void {
    $write = 'throttle:'.TalentPoolServiceProvider::WRITE_LIMITER;

    Route::get('company/talent-pool', [TalentPoolController::class, 'index']);
    Route::post('company/talent-pool', [TalentPoolController::class, 'store'])->middleware($write);

    Route::get('company/talent-pool/categories', [CategoryController::class, 'index']);
    Route::post('company/talent-pool/categories', [CategoryController::class, 'store'])->middleware($write);
    Route::patch('company/talent-pool/categories/{id}', [CategoryController::class, 'update'])->whereNumber('id')->middleware($write);
    Route::delete('company/talent-pool/categories/{id}', [CategoryController::class, 'destroy'])->whereNumber('id')->middleware($write);

    Route::get('company/talent-pool/{id}', [TalentPoolController::class, 'show'])->whereNumber('id');
    Route::patch('company/talent-pool/{id}', [TalentPoolController::class, 'update'])->whereNumber('id')->middleware($write);
    Route::delete('company/talent-pool/{id}', [TalentPoolController::class, 'destroy'])->whereNumber('id')->middleware($write);
});
