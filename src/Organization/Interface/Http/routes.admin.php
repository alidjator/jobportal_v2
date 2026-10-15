<?php

use Illuminate\Support\Facades\Route;
use Src\Company\Application\CompanyPermissions;
use Src\Organization\Interface\Http\Controllers\AdminGroupController;
use Src\Organization\Interface\Http\Controllers\GroupRoleController;

// Creating a group and linking tenants stays with the platform admin, as creating companies does (`companies.manage`).
Route::prefix('admin')->middleware(['auth:admin', 'can:'.CompanyPermissions::COMPANIES_MANAGE])->group(function (): void {
    Route::get('groups', [AdminGroupController::class, 'index']);
    Route::post('groups', [AdminGroupController::class, 'store']);
    Route::get('groups/{id}', [AdminGroupController::class, 'show'])->whereNumber('id');
    Route::post('groups/{id}/suspend', [AdminGroupController::class, 'suspend'])->whereNumber('id');
    Route::post('groups/{id}/restore', [AdminGroupController::class, 'restore'])->whereNumber('id');
    Route::put('groups/{id}/legal-entities/{entityId}/tenant', [AdminGroupController::class, 'linkTenant'])->whereNumber(['id', 'entityId']);
    Route::delete('groups/{id}/legal-entities/{entityId}/tenant', [AdminGroupController::class, 'unlinkTenant'])->whereNumber(['id', 'entityId']);

    Route::get('groups/{id}/members', [GroupRoleController::class, 'index'])->whereNumber('id');
    Route::put('groups/{id}/members/{userId}', [GroupRoleController::class, 'set'])->whereNumber(['id', 'userId']);
    Route::delete('groups/{id}/members/{userId}', [GroupRoleController::class, 'remove'])->whereNumber(['id', 'userId']);
});
