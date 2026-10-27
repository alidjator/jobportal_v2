<?php

use Illuminate\Support\Facades\Route;
use Src\Company\Infrastructure\CompanyServiceProvider;
use Src\Company\Interface\Http\Controllers\CompanyActivityController;
use Src\Company\Interface\Http\Controllers\CompanyDocumentController;
use Src\Company\Interface\Http\Controllers\CompanyImageController;
use Src\Company\Interface\Http\Controllers\CompanyLocationController;
use Src\Company\Interface\Http\Controllers\CompanyNameChangeController;
use Src\Company\Interface\Http\Controllers\CompanyPortalController;
use Src\Company\Interface\Http\Controllers\CompanyProfileController;
use Src\Company\Interface\Http\Controllers\CompanySettingsController;
use Src\Company\Interface\Http\Controllers\CurrentCompanyController;
use Src\Company\Interface\Http\Controllers\ImportSummaryController;
use Src\Company\Interface\Http\Controllers\InvitationController;
use Src\Company\Interface\Http\Controllers\MessageDefaultsController;
use Src\Company\Interface\Http\Controllers\PermissionCatalogController;
use Src\Company\Interface\Http\Controllers\PublicCompanyController;
use Src\Company\Interface\Http\Controllers\TeamInvitationController;
use Src\Company\Interface\Http\Controllers\TeamMemberController;
use Src\Company\Interface\Http\Controllers\TeamSettingsController;

// Public: the invitee has no account yet, the token in the URL is the credential.
Route::get('auth/invitations/{token}', [InvitationController::class, 'show']);
Route::post('auth/invitations/{token}/accept', [InvitationController::class, 'accept']);

// Public company page. A profile edit shows up within a minute; clients revalidate with the ETag.
Route::get('companies/{slug}', [PublicCompanyController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('cache.headers:public;max_age=60;etag');

// The pictures are sent by these routes only (the files have no public URL). The URLs carry `?v=<version>`, so a long cache is safe.
foreach (['logo', 'banner'] as $kind) {
    Route::get("companies/{slug}/{$kind}", [CompanyImageController::class, 'showPublic'])
        ->where('slug', '[a-z0-9-]+')
        ->defaults('kind', $kind)
        ->middleware('cache.headers:public;max_age=3600;etag');
}

Route::middleware('auth:user')->group(function (): void {
    Route::get('company/me', CurrentCompanyController::class);
    Route::get('company/permissions', PermissionCatalogController::class);

    // Logo and banner of the own company: any member sees them, a company admin uploads (a multipart POST: PHP does not read a multipart PUT) and removes them.
    foreach (['logo', 'banner'] as $kind) {
        Route::get("company/{$kind}", [CompanyImageController::class, 'showOwn'])->defaults('kind', $kind)->middleware('cache.headers:private;max_age=3600;etag');
        Route::post("company/{$kind}", [CompanyImageController::class, 'store'])->defaults('kind', $kind);
        Route::delete("company/{$kind}", [CompanyImageController::class, 'destroy'])->defaults('kind', $kind);
    }

    // A verified company's name is locked: a member who may change the profile asks for a change with a PDF that proves it (a multipart POST); one request waits at a time.
    Route::get('company/name-change-requests', [CompanyNameChangeController::class, 'index']);
    Route::post('company/name-change-requests', [CompanyNameChangeController::class, 'store']);
    Route::post('company/name-change-requests/{id}/cancel', [CompanyNameChangeController::class, 'cancel'])->whereNumber('id');

    // The verification documents of the own company; every use case checks that the caller is a company admin of an active company.
    Route::get('company/documents', [CompanyDocumentController::class, 'index']);
    Route::post('company/documents/{typeId}', [CompanyDocumentController::class, 'store'])->whereNumber('typeId');
    Route::delete('company/documents/{typeId}', [CompanyDocumentController::class, 'destroy'])->whereNumber('typeId');
    Route::get('company/documents/{typeId}/file', [CompanyDocumentController::class, 'file'])->whereNumber('typeId');

    // What the karir.pkp import carried for the own company (company admins; `404 no-import-summary` for a company that was not imported).
    Route::get('company/import-summary', [ImportSummaryController::class, 'show']);
    Route::post('company/import-summary/dismiss', [ImportSummaryController::class, 'dismiss']);

    // The own company profile: any member reads it, the use cases check that the caller is a company admin to change it.
    Route::get('company/profile', [CompanyProfileController::class, 'show']);
    Route::patch('company/profile', [CompanyProfileController::class, 'update']);
    Route::put('company/slug', [CompanyProfileController::class, 'changeSlug']);

    // The work locations of the own company: any member reads them (the interview and requisition pickers), a member who may change the profile manages them.
    Route::get('company/locations', [CompanyLocationController::class, 'index']);
    Route::post('company/locations', [CompanyLocationController::class, 'store']);
    Route::patch('company/locations/{id}', [CompanyLocationController::class, 'update'])->whereNumber('id');
    Route::post('company/locations/{id}/main', [CompanyLocationController::class, 'makeMain'])->whereNumber('id');
    Route::post('company/locations/{id}/deactivate', [CompanyLocationController::class, 'deactivate'])->whereNumber('id');
    Route::post('company/locations/{id}/activate', [CompanyLocationController::class, 'activate'])->whereNumber('id');
    Route::delete('company/locations/{id}', [CompanyLocationController::class, 'destroy'])->whereNumber('id');

    // The team of the caller's own company; every use case checks that the caller is a company admin.
    // Open invitations are rows of the team list (`GET company/members`), so there is no list of invitations.
    Route::post('company/invitations', [TeamInvitationController::class, 'store']);
    Route::patch('company/invitations/{id}', [TeamInvitationController::class, 'update'])->whereNumber('id');
    Route::post('company/invitations/{id}/resend', [TeamInvitationController::class, 'resend'])->whereNumber('id');
    Route::delete('company/invitations/{id}', [TeamInvitationController::class, 'destroy'])->whereNumber('id');
    Route::get('company/members', [TeamMemberController::class, 'index']);
    Route::get('company/members/options', [TeamMemberController::class, 'options']);
    Route::get('company/members/{userId}', [TeamMemberController::class, 'show'])->whereNumber('userId');
    Route::get('company/members/{userId}/avatar', [TeamMemberController::class, 'avatar'])->whereNumber('userId');
    Route::get('company/members/{userId}/jobs', [TeamMemberController::class, 'jobs'])->whereNumber('userId');
    Route::get('company/members/{userId}/activity', [TeamMemberController::class, 'activity'])->whereNumber('userId');
    Route::delete('company/members/{userId}', [TeamMemberController::class, 'destroy'])->whereNumber('userId');
    Route::patch('company/members/{userId}', [TeamMemberController::class, 'update'])->whereNumber('userId');
    Route::post('company/members/{userId}/suspend', [TeamMemberController::class, 'suspend'])->whereNumber('userId');
    Route::post('company/members/{userId}/reactivate', [TeamMemberController::class, 'reactivate'])->whereNumber('userId');
    Route::post('company/members/{userId}/reset-two-factor', [TeamMemberController::class, 'resetTwoFactor'])->whereNumber('userId');
    Route::post('company/members/{userId}/make-owner', [TeamMemberController::class, 'makeOwner'])->whereNumber('userId');
    // The company settings page: any member reads, a company admin changes the settings that belong to the company.
    Route::get('company/settings', [CompanySettingsController::class, 'show']);
    Route::patch('company/settings', [CompanySettingsController::class, 'update']);

    // The brand of the career site of the own company (`company.profile`): a draft that is previewed and then published.
    Route::get('company/portal', [CompanyPortalController::class, 'show']);
    Route::put('company/portal/draft', [CompanyPortalController::class, 'saveDraft']);
    Route::delete('company/portal/draft', [CompanyPortalController::class, 'discardDraft']);
    Route::post('company/portal/draft/publish', [CompanyPortalController::class, 'publish']);
    Route::post('company/portal/draft/preview', [CompanyPortalController::class, 'preview']);

    // The default texts that prefill the note of an interview, a test and an offer: any member reads them, a member who may change the profile sets them.
    Route::get('company/message-defaults', [MessageDefaultsController::class, 'show']);
    Route::put('company/message-defaults', [MessageDefaultsController::class, 'update']);

    // The activity log of the company: the entries of its members and of the platform about it; the export is a CSV, limited per hour.
    Route::get('company/activity', [CompanyActivityController::class, 'index']);
    Route::get('company/activity/export', [CompanyActivityController::class, 'export'])->middleware('throttle:'.CompanyServiceProvider::ACTIVITY_EXPORT_LIMITER);
    Route::get('company/team/history', [TeamSettingsController::class, 'history']);
    Route::get('company/team/email-domains', [TeamSettingsController::class, 'emailDomains']);
    Route::put('company/team/email-domains', [TeamSettingsController::class, 'updateEmailDomains']);
});
