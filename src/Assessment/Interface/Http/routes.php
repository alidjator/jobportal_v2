<?php

use Illuminate\Support\Facades\Route;
use Src\Assessment\Interface\Http\AuthoringRoutes;
use Src\Assessment\Interface\Http\Controllers\BatteryController;
use Src\Assessment\Interface\Http\Controllers\CatalogController;
use Src\Assessment\Interface\Http\Controllers\InvitationController;
use Src\Assessment\Interface\Http\Controllers\MyInvitationController;
use Src\Assessment\Interface\Http\Controllers\ProviderProfileController;
use Src\Assessment\Interface\Http\Controllers\PsychologistInvitationController;
use Src\Identity\Interface\Http\Middleware\EnforceTermsAcceptance;

Route::prefix('provider')->group(function (): void {
    // The invitee has no account yet, so the token in the URL is the credential.
    Route::get('invitations/{token}', [PsychologistInvitationController::class, 'show']);
    Route::post('invitations/{token}/accept', [PsychologistInvitationController::class, 'accept']);

    // The consumer terms do not apply to psychologists; the use cases refuse every other kind of account.
    Route::middleware('auth:user')->withoutMiddleware(EnforceTermsAcceptance::class)->group(function (): void {
        Route::get('me', ProviderProfileController::class);

        AuthoringRoutes::register();

        Route::get('tests/{id}/norms', [BatteryController::class, 'norms'])->whereNumber('id');
        Route::put('tests/{id}/norms', [BatteryController::class, 'replaceNorms'])->whereNumber('id');
        Route::get('tests/{id}/report-template', [BatteryController::class, 'reportTemplate'])->whereNumber('id');
        Route::put('tests/{id}/report-template', [BatteryController::class, 'saveReportTemplate'])->whereNumber('id');
    });
});

// A company admin writes the company's skill tests; every member reads the catalogue. The use cases check the membership and the role.
Route::middleware('auth:user')->prefix('company/assessment')->group(function (): void {
    AuthoringRoutes::register();

    Route::get('library', [CatalogController::class, 'library']);
    Route::get('batteries', [CatalogController::class, 'batteries']);
    Route::get('catalog/{id}', [CatalogController::class, 'show'])->whereNumber('id');

    // The tests sent to the candidates, for the members who may see the application.
    Route::get('applications/{id}/invitations', [InvitationController::class, 'index'])->whereNumber('id');
    Route::put('applications/{id}/invitation', [InvitationController::class, 'store'])->whereNumber('id');
    Route::get('invitations/{id}', [InvitationController::class, 'show'])->whereNumber('id');
    Route::post('invitations/{id}/cancel', [InvitationController::class, 'cancel'])->whereNumber('id');
});

// The tests a job seeker was asked to take.
Route::middleware('auth:user')->prefix('me/assessment')->group(function (): void {
    Route::get('invitations', [MyInvitationController::class, 'index']);
    Route::get('invitations/{id}', [MyInvitationController::class, 'show'])->whereNumber('id');
});
