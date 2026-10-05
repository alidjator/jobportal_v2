<?php

use Illuminate\Support\Facades\Route;
use Src\Company\Application\CompanyPermissions;
use Src\Company\Interface\Http\Controllers\AdminCompanyController;
use Src\Company\Interface\Http\Controllers\AdminDocumentTypeController;

Route::prefix('admin')->middleware('auth:admin')->group(function (): void {
    Route::middleware('can:'.CompanyPermissions::COMPANIES_MANAGE)->group(function (): void {
        Route::get('companies', [AdminCompanyController::class, 'index']);
        Route::post('companies', [AdminCompanyController::class, 'store']);
        Route::get('companies/{id}', [AdminCompanyController::class, 'show'])->whereNumber('id');
        Route::post('companies/{id}/suspend', [AdminCompanyController::class, 'suspend'])->whereNumber('id');
        Route::post('companies/{id}/restore', [AdminCompanyController::class, 'restore'])->whereNumber('id');
        Route::post('companies/{id}/invitations', [AdminCompanyController::class, 'inviteCompanyAdmin'])->whereNumber('id');

        Route::get('company-document-types', [AdminDocumentTypeController::class, 'index']);
        Route::post('company-document-types', [AdminDocumentTypeController::class, 'store']);
        Route::put('company-document-types/{id}', [AdminDocumentTypeController::class, 'update'])->whereNumber('id');
    });
});
