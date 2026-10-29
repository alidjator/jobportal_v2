<?php

use Illuminate\Support\Facades\Route;
use Src\Approval\Interface\Http\Controllers\ApprovalController;
use Src\Approval\Interface\Http\Controllers\ApprovalDelegationController;
use Src\Approval\Interface\Http\Controllers\ApprovalFlowController;
use Src\Shared\Interface\Http\Middleware\RequireGroups;

// The approval flows of the group (any member reads, `group.approvals.manage` changes) and of the tenant (any member of an internal tenant reads, `approvals.manage`
// changes). `PUT` saves a flow whole, replacing the one that exists; `DELETE` removes it (a tenant then falls back to its group's flow).
Route::middleware('auth:user')->group(function (): void {
    Route::middleware(RequireGroups::class)->group(function (): void {
        Route::get('organization/approval-flows', [ApprovalFlowController::class, 'groupIndex']);
        Route::put('organization/approval-flows/{subjectType}', [ApprovalFlowController::class, 'groupSave'])->where('subjectType', '[a-z0-9_.]+');
        Route::delete('organization/approval-flows/{subjectType}', [ApprovalFlowController::class, 'groupRemove'])->where('subjectType', '[a-z0-9_.]+');
    });

    Route::get('company/approval-flows', [ApprovalFlowController::class, 'companyIndex']);
    Route::put('company/approval-flows/{subjectType}', [ApprovalFlowController::class, 'companySave'])->where('subjectType', '[a-z0-9_.]+');
    Route::delete('company/approval-flows/{subjectType}', [ApprovalFlowController::class, 'companyRemove'])->where('subjectType', '[a-z0-9_.]+');

    // The approvals the signed-in account takes part in (`?as=approver` the ones waiting for it, `?as=requester` the ones it asked for). Every approver may decide the steps
    // assigned to them (or to an approver who delegated to them, or to a group permission they hold) without a separate permission.
    Route::get('company/approvals', [ApprovalController::class, 'index']);
    Route::get('company/approvals/{id}', [ApprovalController::class, 'show'])->whereNumber('id');
    Route::post('company/approvals/{id}/approve', [ApprovalController::class, 'approve'])->whereNumber('id');
    Route::post('company/approvals/{id}/reject', [ApprovalController::class, 'reject'])->whereNumber('id');
    Route::post('company/approvals/{id}/return', [ApprovalController::class, 'returnForRevision'])->whereNumber('id');
    Route::put('company/approvals/{id}/approvers/{slotId}', [ApprovalController::class, 'replace'])->whereNumber(['id', 'slotId']);

    Route::get('company/approval-delegations', [ApprovalDelegationController::class, 'index']);
    Route::post('company/approval-delegations', [ApprovalDelegationController::class, 'store']);
    Route::delete('company/approval-delegations/{id}', [ApprovalDelegationController::class, 'destroy'])->whereNumber('id');
});
