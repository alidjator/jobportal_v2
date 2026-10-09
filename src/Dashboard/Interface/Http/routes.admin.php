<?php

use Illuminate\Support\Facades\Route;
use Src\Dashboard\Application\AnalyticsPermissions;
use Src\Dashboard\Application\DashboardPermissions;
use Src\Dashboard\Interface\Http\Controllers\AnalyticsController;
use Src\Dashboard\Interface\Http\Controllers\DashboardController;

Route::prefix('admin')->middleware('auth:admin')->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'admin'])->middleware('can:'.DashboardPermissions::DASHBOARD_VIEW);
    Route::get('analytics/{report}', [AnalyticsController::class, 'show'])->where('report', '[a-z_]+')->middleware('can:'.AnalyticsPermissions::ANALYTICS_VIEW);
});
