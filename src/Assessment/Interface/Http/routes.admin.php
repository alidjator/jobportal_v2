<?php

use Illuminate\Support\Facades\Route;
use Src\Assessment\Application\AssessmentPermissions;
use Src\Assessment\Interface\Http\AuthoringRoutes;
use Src\Assessment\Interface\Http\Controllers\AdminCompanyBatteryController;
use Src\Assessment\Interface\Http\Controllers\AdminProviderController;
use Src\Assessment\Interface\Http\Controllers\AdminProviderRegisterController;
use Src\Assessment\Interface\Http\Controllers\AdminPsychologistController;
use Src\Assessment\Interface\Http\Controllers\AdminPsychologistInvitationController;

Route::prefix('admin')->middleware('auth:admin')->group(function (): void {
    Route::middleware('can:'.AssessmentPermissions::PROVIDERS_MANAGE)->group(function (): void {
        Route::get('assessment/providers', [AdminProviderController::class, 'index']);
        Route::post('assessment/providers', [AdminProviderController::class, 'store']);
        Route::get('assessment/providers/{id}', [AdminProviderController::class, 'show'])->whereNumber('id');
        Route::put('assessment/providers/{id}', [AdminProviderController::class, 'update'])->whereNumber('id');
        Route::put('assessment/providers/{id}/auto-issue', [AdminProviderController::class, 'autoIssue'])->whereNumber('id');
        Route::post('assessment/providers/{id}/suspend', [AdminProviderController::class, 'suspend'])->whereNumber('id');
        Route::post('assessment/providers/{id}/restore', [AdminProviderController::class, 'restore'])->whereNumber('id');

        Route::get('assessment/providers/{id}/register', [AdminProviderRegisterController::class, 'index'])->whereNumber('id');
        Route::get('assessment/providers/{id}/psychologists', [AdminPsychologistController::class, 'index'])->whereNumber('id');
        Route::put('assessment/psychologists/{id}', [AdminPsychologistController::class, 'update'])->whereNumber('id');
        Route::post('assessment/psychologists/{id}/suspend', [AdminPsychologistController::class, 'suspend'])->whereNumber('id');
        Route::post('assessment/psychologists/{id}/restore', [AdminPsychologistController::class, 'restore'])->whereNumber('id');
        Route::post('assessment/psychologists/{id}/reset-two-factor', [AdminPsychologistController::class, 'resetTwoFactor'])->whereNumber('id');

        Route::get('assessment/providers/{id}/invitations', [AdminPsychologistInvitationController::class, 'index'])->whereNumber('id');
        Route::post('assessment/providers/{id}/invitations', [AdminPsychologistInvitationController::class, 'store'])->whereNumber('id');
        Route::delete('assessment/providers/{id}/invitations/{invitationId}', [AdminPsychologistInvitationController::class, 'destroy'])->whereNumber(['id', 'invitationId']);

        Route::get('assessment/batteries', [AdminCompanyBatteryController::class, 'published']);
        Route::get('assessment/companies/{id}/batteries', [AdminCompanyBatteryController::class, 'index'])->whereNumber('id');
        Route::post('assessment/companies/{id}/batteries', [AdminCompanyBatteryController::class, 'store'])->whereNumber('id');
        Route::delete('assessment/companies/{id}/batteries/{testId}', [AdminCompanyBatteryController::class, 'destroy'])->whereNumber(['id', 'testId']);
    });

    Route::middleware('can:'.AssessmentPermissions::LIBRARY_MANAGE)->prefix('assessment/library')->group(function (): void {
        AuthoringRoutes::register();
    });
});
