<?php

use Illuminate\Support\Facades\Route;
use Src\Dashboard\Interface\Http\Controllers\AnalyticsController;
use Src\Job\Interface\Http\Controllers\CompanyJobController;
use Src\Organization\Interface\Http\Controllers\GroupActivityController;
use Src\Organization\Interface\Http\Controllers\GroupPortalController;
use Src\Organization\Interface\Http\Controllers\GroupRoleController;
use Src\Organization\Interface\Http\Controllers\GroupSettingsController;
use Src\Organization\Interface\Http\Controllers\GroupTenantController;
use Src\Organization\Interface\Http\Controllers\OrganizationController;
use Src\Organization\Interface\Http\Controllers\PositionController;
use Src\Organization\Interface\Http\Controllers\StructureCatalogController;
use Src\Organization\Interface\Http\Controllers\SupportGrantController;
use Src\Organization\Interface\Http\Controllers\UnitController;
use Src\Organization\Interface\Http\Middleware\ReadAsGroup;
use Src\Recruitment\Interface\Http\Controllers\CompanyApplicationController;

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

    // The brand of the career site of the group (`group.settings.manage`): a draft that is previewed and then published.
    Route::get('organization/portal', [GroupPortalController::class, 'show']);
    Route::put('organization/portal/draft', [GroupPortalController::class, 'saveDraft']);
    Route::delete('organization/portal/draft', [GroupPortalController::class, 'discardDraft']);
    Route::post('organization/portal/draft/publish', [GroupPortalController::class, 'publish']);
    Route::post('organization/portal/draft/preview', [GroupPortalController::class, 'preview']);

    // Temporary support access for a named platform admin (`group.members.manage`): who may receive it, who has it, give it for 1 to 72 hours, end it at once.
    Route::get('organization/support-admins', [SupportGrantController::class, 'admins']);
    Route::get('organization/support-grants', [SupportGrantController::class, 'index']);
    Route::post('organization/support-grants', [SupportGrantController::class, 'store']);
    Route::delete('organization/support-grants/{id}', [SupportGrantController::class, 'destroy'])->whereNumber('id');

    // Reading the recruitment of the group's tenants (`group.recruitment.view`): the tenants, then the recruiter's own read routes with `?tenant_id=<company>`, answered as a read-only
    // member of that tenant. Only GET routes exist here; reads of applicants are audited. Acting on applications still needs a membership of that tenant.
    Route::get('organization/tenants', [GroupTenantController::class, 'index']);

    Route::middleware(ReadAsGroup::class)->group(function (): void {
        Route::get('organization/read/jobs', [CompanyJobController::class, 'index']);
        Route::get('organization/read/jobs/{id}', [CompanyJobController::class, 'show'])->whereNumber('id');
        Route::get('organization/read/jobs/{id}/performance', [CompanyJobController::class, 'performance'])->whereNumber('id');
        Route::get('organization/read/analytics/{report}', [AnalyticsController::class, 'show'])->where('report', '[a-z_]+');
    });

    Route::middleware(ReadAsGroup::class.':audited')->group(function (): void {
        Route::get('organization/read/jobs/{id}/applications', [CompanyApplicationController::class, 'index'])->whereNumber('id');
        Route::get('organization/read/applications/{id}', [CompanyApplicationController::class, 'show'])->whereNumber('id');
        Route::get('organization/read/applications/{id}/resume', [CompanyApplicationController::class, 'resume'])->whereNumber('id');
    });

    Route::get('organization/members', [GroupRoleController::class, 'index']);
    Route::put('organization/members/{userId}', [GroupRoleController::class, 'set'])->whereNumber('userId');
    Route::delete('organization/members/{userId}', [GroupRoleController::class, 'remove'])->whereNumber('userId');
});
