<?php

use Illuminate\Support\Facades\Route;
use Src\JobInvitation\Infrastructure\JobInvitationServiceProvider;
use Src\JobInvitation\Interface\Http\Controllers\CompanyEmailInvitationController;
use Src\JobInvitation\Interface\Http\Controllers\CompanyInvitationController;
use Src\JobInvitation\Interface\Http\Controllers\EmailInvitationLinkController;
use Src\JobInvitation\Interface\Http\Controllers\MyInvitationController;

// The person's link of an invitation by email: public, read by the token; the limiter keeps the token from being guessed.
Route::get('email-invitations/{token}', [EmailInvitationLinkController::class, 'show'])->middleware('throttle:'.JobInvitationServiceProvider::TOKEN_LIMITER);
Route::post('email-invitations/{token}/decline', [EmailInvitationLinkController::class, 'decline'])->middleware('throttle:'.JobInvitationServiceProvider::TOKEN_LIMITER);

// Every use case checks the caller: a member of an active company on the company side, a job seeker on the other.
Route::middleware('auth:user')->group(function (): void {
    Route::get('company/jobs/{id}/invitations', [CompanyInvitationController::class, 'index'])->whereNumber('id');
    Route::post('company/jobs/{id}/invitations', [CompanyInvitationController::class, 'store'])->whereNumber('id');
    Route::post('company/jobs/{id}/invitations/bulk', [CompanyInvitationController::class, 'bulk'])->whereNumber('id');

    Route::get('company/jobs/{id}/email-invitations', [CompanyEmailInvitationController::class, 'index'])->whereNumber('id');
    Route::post('company/jobs/{id}/email-invitations', [CompanyEmailInvitationController::class, 'store'])->whereNumber('id');
    Route::get('company/email-invitations/{id}/cv', [CompanyEmailInvitationController::class, 'cv'])->whereNumber('id');
    Route::post('company/email-invitations/{id}/resend', [CompanyEmailInvitationController::class, 'resend'])->whereNumber('id');
    Route::post('company/email-invitations/{id}/cancel', [CompanyEmailInvitationController::class, 'cancel'])->whereNumber('id');

    Route::post('me/email-invitations/{token}/claim', [EmailInvitationLinkController::class, 'claim'])->middleware('throttle:'.JobInvitationServiceProvider::TOKEN_LIMITER);
    Route::post('me/email-invitations/{token}/use-cv', [EmailInvitationLinkController::class, 'useCv'])->middleware('throttle:'.JobInvitationServiceProvider::TOKEN_LIMITER);
    Route::post('me/email-invitations/{token}/discard-cv', [EmailInvitationLinkController::class, 'discardCv'])->middleware('throttle:'.JobInvitationServiceProvider::TOKEN_LIMITER);

    Route::get('me/invitations', [MyInvitationController::class, 'index']);
    Route::get('me/invitations/{id}', [MyInvitationController::class, 'show'])->whereNumber('id');
    Route::post('me/invitations/{id}/decline', [MyInvitationController::class, 'decline'])->whereNumber('id');
});
