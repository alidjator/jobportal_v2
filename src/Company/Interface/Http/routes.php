<?php

use Illuminate\Support\Facades\Route;
use Src\Company\Interface\Http\Controllers\CurrentCompanyController;
use Src\Company\Interface\Http\Controllers\InvitationController;

// Public: the invitee has no account yet, the token in the URL is the credential.
Route::get('auth/invitations/{token}', [InvitationController::class, 'show']);
Route::post('auth/invitations/{token}/accept', [InvitationController::class, 'accept']);

Route::middleware('auth:user')->group(function (): void {
    Route::get('company/me', CurrentCompanyController::class);
});
