<?php

use Illuminate\Support\Facades\Route;
use Src\Identity\Application\IdentityPermissions;
use Src\Identity\Infrastructure\IdentityServiceProvider;
use Src\Identity\Interface\Http\Controllers\AdminActivationController;
use Src\Identity\Interface\Http\Controllers\AdminUserActionController;
use Src\Identity\Interface\Http\Controllers\AdminUserController;
use Src\Identity\Interface\Http\Controllers\ChangePasswordController;
use Src\Identity\Interface\Http\Controllers\CurrentAdminController;
use Src\Identity\Interface\Http\Controllers\RecoveryCodesController;
use Src\Identity\Interface\Http\Controllers\SignInAdminController;
use Src\Identity\Interface\Http\Controllers\SignOutController;
use Src\Identity\Interface\Http\Controllers\SignOutEverywhereController;
use Src\Identity\Interface\Http\Controllers\TwoFactorSignInController;

Route::prefix('admin')->group(function (): void {
    Route::post('auth/login', SignInAdminController::class);
    Route::post('auth/two-factor', TwoFactorSignInController::class);

    Route::middleware('auth:admin')->group(function (): void {
        Route::post('auth/logout', SignOutController::class);
        Route::post('auth/logout-all', SignOutEverywhereController::class);
        Route::put('me/password', ChangePasswordController::class);
        Route::post('me/two-factor/recovery-codes', RecoveryCodesController::class);
        Route::get('me', CurrentAdminController::class);

        Route::middleware('can:'.IdentityPermissions::USERS_VIEW)->group(function (): void {
            Route::get('users', [AdminUserController::class, 'index']);
            Route::get('users/{id}', [AdminUserController::class, 'show'])->whereNumber('id');
            Route::get('activation/report', [AdminActivationController::class, 'report']);
        });

        Route::middleware('can:'.IdentityPermissions::USERS_MANAGE)->prefix('users/{id}')->whereNumber('id')->group(function (): void {
            Route::put('email', [AdminUserActionController::class, 'correctEmail']);
            Route::post('reset-two-factor', [AdminUserActionController::class, 'resetTwoFactor']);

            Route::middleware('throttle:'.IdentityServiceProvider::SUPPORT_ACTION_LIMITER)->group(function (): void {
                Route::post('activation-email', [AdminUserActionController::class, 'activationEmail']);
                Route::post('password-reset', [AdminUserActionController::class, 'passwordReset']);
                Route::post('resend-verification', [AdminUserActionController::class, 'resendVerification']);
            });
        });
    });
});
