<?php

use Illuminate\Support\Facades\Route;
use Src\JobInvitation\Interface\Http\Controllers\CompanyInvitationController;
use Src\JobInvitation\Interface\Http\Controllers\MyInvitationController;

// Every use case checks the caller: a member of an active company on the company side, a job seeker on the other.
Route::middleware('auth:user')->group(function (): void {
    Route::get('company/jobs/{id}/invitations', [CompanyInvitationController::class, 'index'])->whereNumber('id');
    Route::post('company/jobs/{id}/invitations', [CompanyInvitationController::class, 'store'])->whereNumber('id');

    Route::get('me/invitations', [MyInvitationController::class, 'index']);
    Route::get('me/invitations/{id}', [MyInvitationController::class, 'show'])->whereNumber('id');
    Route::post('me/invitations/{id}/decline', [MyInvitationController::class, 'decline'])->whereNumber('id');
});
