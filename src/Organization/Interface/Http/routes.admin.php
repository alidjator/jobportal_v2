<?php

use Illuminate\Support\Facades\Route;
use Src\Company\Application\CompanyPermissions;
use Src\Organization\Application\OrganizationPermissions;
use Src\Organization\Interface\Http\Controllers\AdminGroupController;
use Src\Organization\Interface\Http\Controllers\GroupActivityController;
use Src\Organization\Interface\Http\Controllers\GroupRoleController;
use Src\Organization\Interface\Http\Controllers\OrganizationController;
use Src\Organization\Interface\Http\Controllers\PositionController;
use Src\Organization\Interface\Http\Controllers\StructureCatalogController;
use Src\Organization\Interface\Http\Controllers\SupportGrantController;
use Src\Organization\Interface\Http\Controllers\UnitController;
use Src\Organization\Interface\Http\Middleware\ActAsSupport;
use Src\Shared\Interface\Http\Middleware\RequireGroups;

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

// Temporary support access (`erp-governance.md` section 1.1): a platform admin with `groups.support` reads a group's structure and activity log only while a group admin's
// grant to them is active, always with `?group_id=<group>`, read only, and every read is audited. The paths mirror the group member's reads under `organization/`.
Route::prefix('admin/support')->middleware(['auth:admin', 'can:'.OrganizationPermissions::GROUPS_SUPPORT, RequireGroups::class])->group(function (): void {
    Route::get('groups', [SupportGrantController::class, 'groupsOfAdmin']);

    Route::middleware(ActAsSupport::class)->group(function (): void {
        Route::get('group', [OrganizationController::class, 'group']);
        Route::get('activity', [GroupActivityController::class, 'index']);
        Route::get('grades', [StructureCatalogController::class, 'grades']);
        Route::get('unit-types', [StructureCatalogController::class, 'unitTypes']);
        Route::get('cost-centres', [StructureCatalogController::class, 'costCentres']);

        foreach (['units' => UnitController::class, 'positions' => PositionController::class] as $path => $controller) {
            Route::get($path, [$controller, 'index']);
            Route::get("{$path}/{id}", [$controller, 'show'])->whereNumber('id');
            Route::get("{$path}/{id}/versions", [$controller, 'versions'])->whereNumber('id');
        }
    });
});
