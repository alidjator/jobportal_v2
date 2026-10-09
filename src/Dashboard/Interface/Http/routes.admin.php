<?php

use Illuminate\Support\Facades\Route;
use Src\Dashboard\Application\DashboardPermissions;
use Src\Dashboard\Interface\Http\Controllers\DashboardController;

Route::prefix('admin')->middleware(['auth:admin', 'can:'.DashboardPermissions::DASHBOARD_VIEW])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'admin']);
});
