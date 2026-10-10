<?php

use Illuminate\Support\Facades\Route;
use Src\Identity\Interface\Http\Controllers\AvatarController;
use Src\Identity\Interface\Http\Controllers\CancelAccountDeletionController;
use Src\Identity\Interface\Http\Controllers\ChangePasswordController;
use Src\Identity\Interface\Http\Controllers\CurrentUserController;
use Src\Identity\Interface\Http\Controllers\ForgotPasswordController;
use Src\Identity\Interface\Http\Controllers\RegisterJobSeekerController;
use Src\Identity\Interface\Http\Controllers\RequestAccountDeletionController;
use Src\Identity\Interface\Http\Controllers\ResendEmailVerificationCodeController;
use Src\Identity\Interface\Http\Controllers\ResetPasswordController;
use Src\Identity\Interface\Http\Controllers\SignInUserController;
use Src\Identity\Interface\Http\Controllers\SignInWithGoogleController;
use Src\Identity\Interface\Http\Controllers\SignOutController;
use Src\Identity\Interface\Http\Controllers\SignOutEverywhereController;
use Src\Identity\Interface\Http\Controllers\TermsAcceptanceController;
use Src\Identity\Interface\Http\Controllers\VerifyEmailController;
use Src\Identity\Interface\Http\Middleware\EnforceTermsAcceptance;

Route::post('auth/register', RegisterJobSeekerController::class);
Route::post('auth/login', SignInUserController::class);
Route::post('auth/google', SignInWithGoogleController::class);
Route::post('auth/account/restore', CancelAccountDeletionController::class);
Route::post('auth/password/forgot', ForgotPasswordController::class);
Route::post('auth/password/reset', ResetPasswordController::class);

Route::middleware('auth:user')->group(function (): void {
    Route::post('auth/email/verify', VerifyEmailController::class);
    Route::post('auth/email/resend', ResendEmailVerificationCodeController::class);

    Route::put('me/password', ChangePasswordController::class);

    Route::get('me/avatar', [AvatarController::class, 'show']);
    Route::post('me/avatar', [AvatarController::class, 'store']);
    Route::delete('me/avatar', [AvatarController::class, 'destroy']);

    // The routes a user may use without having accepted the current terms: read the own profile, accept the terms, sign out (here or everywhere)
    // and delete the account (the user can always leave and exercise the right over their data).
    Route::withoutMiddleware(EnforceTermsAcceptance::class)->group(function (): void {
        Route::post('auth/logout', SignOutController::class);
        Route::post('auth/logout-all', SignOutEverywhereController::class);
        Route::get('me', CurrentUserController::class);
        Route::post('me/deletion', RequestAccountDeletionController::class);
        Route::get('me/terms-acceptance', [TermsAcceptanceController::class, 'show']);
        Route::post('me/terms-acceptance', [TermsAcceptanceController::class, 'store']);
    });
});
