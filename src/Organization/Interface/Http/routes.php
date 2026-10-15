<?php

use Illuminate\Support\Facades\Route;
use Src\Organization\Interface\Http\Controllers\GroupRoleController;
use Src\Organization\Interface\Http\Controllers\OrganizationController;

// The group of the signed-in company member. Every use case checks that the caller holds a group role in a group that is not suspended, and the permission the
// action needs (`group.structure.manage` for the legal entities, `group.members.manage` for the group roles).
Route::middleware('auth:user')->group(function (): void {
    Route::get('organization/group', [OrganizationController::class, 'group']);
    Route::post('organization/legal-entities', [OrganizationController::class, 'storeLegalEntity']);
    Route::patch('organization/legal-entities/{id}', [OrganizationController::class, 'updateLegalEntity'])->whereNumber('id');
    Route::post('organization/legal-entities/{id}/deactivate', [OrganizationController::class, 'deactivateLegalEntity'])->whereNumber('id');
    Route::post('organization/legal-entities/{id}/reactivate', [OrganizationController::class, 'reactivateLegalEntity'])->whereNumber('id');

    Route::get('organization/members', [GroupRoleController::class, 'index']);
    Route::put('organization/members/{userId}', [GroupRoleController::class, 'set'])->whereNumber('userId');
    Route::delete('organization/members/{userId}', [GroupRoleController::class, 'remove'])->whereNumber('userId');
});
