<?php

use Illuminate\Support\Facades\Route;
use Src\Identity\Interface\Http\Controllers\PsychologistTwoFactorSignInController;
use Src\Identity\Interface\Http\Controllers\SignInPsychologistController;
use Src\Identity\Interface\Http\Controllers\SignOutController;
use Src\Identity\Interface\Http\Controllers\SignOutEverywhereController;
use Src\Identity\Interface\Http\Middleware\EnforceTermsAcceptance;

// The sign-in of the psychologists of a partner provider. They use the `user` guard, but the consumer terms do not apply to them.
Route::prefix('provider')->group(function (): void {
    Route::post('auth/login', SignInPsychologistController::class);
    Route::post('auth/two-factor', PsychologistTwoFactorSignInController::class);

    Route::middleware('auth:user')->withoutMiddleware(EnforceTermsAcceptance::class)->group(function (): void {
        Route::post('auth/logout', SignOutController::class);
        Route::post('auth/logout-all', SignOutEverywhereController::class);
    });
});
