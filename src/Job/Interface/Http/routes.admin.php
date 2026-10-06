<?php

use Illuminate\Support\Facades\Route;
use Src\Job\Application\JobPermissions;
use Src\Job\Interface\Http\Controllers\AdminJobController;

Route::prefix('admin')->middleware(['auth:admin', 'can:'.JobPermissions::JOBS_MANAGE])->group(function (): void {
    Route::get('jobs', [AdminJobController::class, 'index']);
    Route::get('jobs/{id}', [AdminJobController::class, 'show'])->whereNumber('id');
    Route::post('jobs/{id}/approve', [AdminJobController::class, 'approve'])->whereNumber('id');
    Route::post('jobs/{id}/reject', [AdminJobController::class, 'reject'])->whereNumber('id');
    Route::post('jobs/{id}/take-down', [AdminJobController::class, 'takeDown'])->whereNumber('id');
    Route::post('jobs/{id}/restore', [AdminJobController::class, 'restore'])->whereNumber('id');
});
