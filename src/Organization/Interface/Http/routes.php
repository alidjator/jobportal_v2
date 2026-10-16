<?php

use Illuminate\Support\Facades\Route;
use Src\Organization\Interface\Http\Controllers\GroupActivityController;
use Src\Organization\Interface\Http\Controllers\GroupRoleController;
use Src\Organization\Interface\Http\Controllers\GroupSettingsController;
use Src\Organization\Interface\Http\Controllers\OrganizationController;
use Src\Organization\Interface\Http\Controllers\PositionController;
use Src\Organization\Interface\Http\Controllers\StructureCatalogController;
use Src\Organization\Interface\Http\Controllers\SupportGrantController;
use Src\Organization\Interface\Http\Controllers\UnitController;

// The group of the signed-in company member. Every use case checks that the caller holds a group role in a group that is not suspended, and the permission the
// action needs (`group.structure.manage` for the legal entities, `group.members.manage` for the group roles).
Route::middleware('auth:user')->group(function (): void {
    Route::get('organization/group', [OrganizationController::class, 'group']);
    Route::post('organization/legal-entities', [OrganizationController::class, 'storeLegalEntity']);
    Route::patch('organization/legal-entities/{id}', [OrganizationController::class, 'updateLegalEntity'])->whereNumber('id');
    Route::post('organization/legal-entities/{id}/deactivate', [OrganizationController::class, 'deactivateLegalEntity'])->whereNumber('id');
    Route::post('organization/legal-entities/{id}/reactivate', [OrganizationController::class, 'reactivateLegalEntity'])->whereNumber('id');

    // The structure of the group (`erp-organization.md` sections 5 and 6): any group member reads, `group.structure.manage` changes. Units and positions are effective-dated:
    // a change is a new version from `valid_from`, and reads take `as_of`.
    foreach (['grades' => ['Grade', 'grades'], 'unit-types' => ['UnitType', 'unitTypes'], 'cost-centres' => ['CostCentre', 'costCentres']] as $path => [$name, $list]) {
        Route::get("organization/{$path}", [StructureCatalogController::class, $list]);
        Route::post("organization/{$path}", [StructureCatalogController::class, 'store'.$name]);
        Route::patch("organization/{$path}/{id}", [StructureCatalogController::class, 'update'.$name])->whereNumber('id');
        Route::post("organization/{$path}/{id}/deactivate", [StructureCatalogController::class, 'deactivate'.$name])->whereNumber('id');
        Route::post("organization/{$path}/{id}/reactivate", [StructureCatalogController::class, 'reactivate'.$name])->whereNumber('id');
    }

    foreach (['units' => UnitController::class, 'positions' => PositionController::class] as $path => $controller) {
        Route::get("organization/{$path}", [$controller, 'index']);
        Route::post("organization/{$path}", [$controller, 'store']);
        Route::get("organization/{$path}/{id}", [$controller, 'show'])->whereNumber('id');
        Route::get("organization/{$path}/{id}/versions", [$controller, 'versions'])->whereNumber('id');
        Route::patch("organization/{$path}/{id}", [$controller, 'update'])->whereNumber('id');
        Route::post("organization/{$path}/{id}/deactivate", [$controller, 'deactivate'])->whereNumber('id');
        Route::post("organization/{$path}/{id}/reactivate", [$controller, 'reactivate'])->whereNumber('id');
    }

    // The activity log of the group (`group.audit.view`): its own changes, its internal tenants' members and the platform's actions on it; no IP address.
    Route::get('organization/activity', [GroupActivityController::class, 'index']);

    // The settings page of the group: any member reads, `group.settings.manage` changes the settings that belong to the group.
    Route::get('organization/settings', [GroupSettingsController::class, 'show']);
    Route::patch('organization/settings', [GroupSettingsController::class, 'update']);

    // Temporary support access for a named platform admin (`group.members.manage`): who may receive it, who has it, give it for 1 to 72 hours, end it at once.
    Route::get('organization/support-admins', [SupportGrantController::class, 'admins']);
    Route::get('organization/support-grants', [SupportGrantController::class, 'index']);
    Route::post('organization/support-grants', [SupportGrantController::class, 'store']);
    Route::delete('organization/support-grants/{id}', [SupportGrantController::class, 'destroy'])->whereNumber('id');

    Route::get('organization/members', [GroupRoleController::class, 'index']);
    Route::put('organization/members/{userId}', [GroupRoleController::class, 'set'])->whereNumber('userId');
    Route::delete('organization/members/{userId}', [GroupRoleController::class, 'remove'])->whereNumber('userId');
});
