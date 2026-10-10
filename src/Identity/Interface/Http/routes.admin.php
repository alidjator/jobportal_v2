<?php

use Illuminate\Support\Facades\Route;
use Src\Identity\Interface\Http\Controllers\ChangePasswordController;
use Src\Identity\Interface\Http\Controllers\CurrentAdminController;
use Src\Identity\Interface\Http\Controllers\SignInAdminController;
use Src\Identity\Interface\Http\Controllers\SignOutController;
use Src\Identity\Interface\Http\Controllers\SignOutEverywhereController;

Route::prefix('admin')->group(function (): void {
    Route::post('auth/login', SignInAdminController::class);

    Route::middleware('auth:admin')->group(function (): void {
        Route::post('auth/logout', SignOutController::class);
        Route::post('auth/logout-all', SignOutEverywhereController::class);
        Route::put('me/password', ChangePasswordController::class);
        Route::get('me', CurrentAdminController::class);
    });
});
