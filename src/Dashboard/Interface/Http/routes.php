<?php

use Illuminate\Support\Facades\Route;
use Src\Dashboard\Interface\Http\Controllers\AnalyticsController;
use Src\Dashboard\Interface\Http\Controllers\DashboardController;

// The summary of the signed-in job seeker, and of the signed-in company member (scoped to the jobs they may see).
Route::middleware('auth:user')->group(function (): void {
    Route::get('me/dashboard', [DashboardController::class, 'jobSeeker']);
    Route::get('company/dashboard', [DashboardController::class, 'company']);

    // The analyses of the company; only a company admin may read them (the use case checks it).
    Route::get('company/analytics/{report}', [AnalyticsController::class, 'show'])->where('report', '[a-z_]+');
});
