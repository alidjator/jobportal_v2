<?php

use Illuminate\Support\Facades\Route;
use Src\Company\Interface\Http\Controllers\CurrentCompanyController;
use Src\Company\Interface\Http\Controllers\InvitationController;
use Src\Company\Interface\Http\Controllers\TeamInvitationController;
use Src\Company\Interface\Http\Controllers\TeamMemberController;

// Public: the invitee has no account yet, the token in the URL is the credential.
Route::get('auth/invitations/{token}', [InvitationController::class, 'show']);
Route::post('auth/invitations/{token}/accept', [InvitationController::class, 'accept']);

Route::middleware('auth:user')->group(function (): void {
    Route::get('company/me', CurrentCompanyController::class);

    // The team of the caller's own company; every use case checks that the caller is a company admin.
    Route::get('company/invitations', [TeamInvitationController::class, 'index']);
    Route::post('company/invitations', [TeamInvitationController::class, 'store']);
    Route::delete('company/invitations/{id}', [TeamInvitationController::class, 'destroy'])->whereNumber('id');
    Route::get('company/members', [TeamMemberController::class, 'index']);
    Route::delete('company/members/{userId}', [TeamMemberController::class, 'destroy'])->whereNumber('userId');
    Route::put('company/members/{userId}/role', [TeamMemberController::class, 'changeRole'])->whereNumber('userId');
});
