<?php

use Illuminate\Support\Facades\Route;
use Src\Company\Application\CompanyPermissions;
use Src\Company\Interface\Http\Controllers\AdminCompanyController;

Route::prefix('admin')->middleware('auth:admin')->group(function (): void {
    Route::middleware('can:'.CompanyPermissions::COMPANIES_MANAGE)->group(function (): void {
        Route::post('companies', [AdminCompanyController::class, 'store']);
        Route::post('companies/{id}/invitations', [AdminCompanyController::class, 'inviteCompanyAdmin'])->whereNumber('id');
    });
});
