<?php

use Illuminate\Support\Facades\Route;
use Src\Job\Application\JobPermissions;
use Src\Job\Interface\Http\Controllers\AdminJobController;
use Src\Job\Interface\Http\Controllers\AdminJobReportController;

Route::prefix('admin')->middleware(['auth:admin', 'can:'.JobPermissions::JOBS_MANAGE])->group(function (): void {
    Route::get('jobs', [AdminJobController::class, 'index']);
    Route::get('jobs/status-counts', [AdminJobController::class, 'statusCounts']);
    Route::get('jobs/{id}', [AdminJobController::class, 'show'])->whereNumber('id');
    Route::post('jobs/{id}/approve', [AdminJobController::class, 'approve'])->whereNumber('id');
    Route::post('jobs/{id}/reject', [AdminJobController::class, 'reject'])->whereNumber('id');
    Route::post('jobs/{id}/take-down', [AdminJobController::class, 'takeDown'])->whereNumber('id');
    Route::post('jobs/{id}/restore', [AdminJobController::class, 'restore'])->whereNumber('id');
    Route::get('job-reports', [AdminJobReportController::class, 'index']);
    Route::get('jobs/{id}/reports', [AdminJobReportController::class, 'ofJob'])->whereNumber('id');
    Route::post('jobs/{id}/reports/dismiss', [AdminJobReportController::class, 'dismiss'])->whereNumber('id');
});
