<?php

use Illuminate\Support\Facades\Route;
use Src\Platform\Application\PlatformPermissions;
use Src\Platform\Interface\Http\Controllers\AdminCompanySettingController;
use Src\Platform\Interface\Http\Controllers\AdminEmailSuppressionController;
use Src\Platform\Interface\Http\Controllers\AdminEmailTemplateController;
use Src\Platform\Interface\Http\Controllers\AdminPortalController;
use Src\Platform\Interface\Http\Controllers\AdminSettingController;
use Src\Platform\Interface\Http\Controllers\AuditLogController;

Route::prefix('admin')->middleware('auth:admin')->group(function (): void {
    Route::middleware('can:'.PlatformPermissions::AUDIT_LOGS_VIEW)->group(function (): void {
        Route::get('audit-logs', [AuditLogController::class, 'index']);
    });

    Route::middleware('can:'.PlatformPermissions::EMAIL_TEMPLATES_MANAGE)->group(function (): void {
        Route::get('email-templates', [AdminEmailTemplateController::class, 'index']);
        Route::get('email-templates/{key}', [AdminEmailTemplateController::class, 'show'])->where('key', '[a-z][a-z0-9_.]*');
        Route::post('email-templates/{key}/preview', [AdminEmailTemplateController::class, 'preview'])->where('key', '[a-z][a-z0-9_.]*');
        Route::put('email-templates/{key}/{locale}', [AdminEmailTemplateController::class, 'update'])->where(['key' => '[a-z][a-z0-9_.]*', 'locale' => 'id|en']);
        Route::delete('email-templates/{key}/{locale}', [AdminEmailTemplateController::class, 'destroy'])->where(['key' => '[a-z][a-z0-9_.]*', 'locale' => 'id|en']);
    });

    // The identity of the career site (marketplace or a company site): a draft that is previewed and then published.
    Route::middleware('can:'.PlatformPermissions::PORTAL_MANAGE)->group(function (): void {
        Route::get('portal', [AdminPortalController::class, 'show']);
        Route::put('portal/draft', [AdminPortalController::class, 'saveDraft']);
        Route::delete('portal/draft', [AdminPortalController::class, 'discardDraft']);
        Route::post('portal/draft/publish', [AdminPortalController::class, 'publish']);
        Route::post('portal/draft/preview', [AdminPortalController::class, 'preview']);
    });

    Route::middleware('can:'.PlatformPermissions::SETTINGS_MANAGE)->group(function (): void {
        Route::get('settings', [AdminSettingController::class, 'index']);
        Route::put('settings/{key}', [AdminSettingController::class, 'update'])->where('key', '[a-z][a-z0-9_.]*');
        Route::delete('settings/{key}', [AdminSettingController::class, 'destroy'])->where('key', '[a-z][a-z0-9_.]*');

        Route::get('email-suppressions', [AdminEmailSuppressionController::class, 'index']);
        Route::post('email-suppressions', [AdminEmailSuppressionController::class, 'store']);
        Route::delete('email-suppressions/{id}', [AdminEmailSuppressionController::class, 'destroy'])->whereNumber('id');

        // The company's own values of the overridable settings (the company must exist: checked through the shared company directory).
        Route::get('companies/{id}/settings', [AdminCompanySettingController::class, 'index'])->whereNumber('id');
        Route::put('companies/{id}/settings/{key}', [AdminCompanySettingController::class, 'update'])->whereNumber('id')->where('key', '[a-z][a-z0-9_.]*');
        Route::delete('companies/{id}/settings/{key}', [AdminCompanySettingController::class, 'destroy'])->whereNumber('id')->where('key', '[a-z][a-z0-9_.]*');
    });
});
