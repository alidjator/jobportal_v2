<?php

use Illuminate\Support\Facades\Route;
use Src\Approval\Interface\Http\Controllers\ApprovalFlowController;

// The approval flows of the group (any member reads, `group.approvals.manage` changes) and of the tenant (any member of an internal tenant reads, `approvals.manage`
// changes). `PUT` saves a flow whole, replacing the one that exists; `DELETE` removes it (a tenant then falls back to its group's flow).
Route::middleware('auth:user')->group(function (): void {
    Route::get('organization/approval-flows', [ApprovalFlowController::class, 'groupIndex']);
    Route::put('organization/approval-flows/{subjectType}', [ApprovalFlowController::class, 'groupSave'])->where('subjectType', '[a-z0-9_.]+');
    Route::delete('organization/approval-flows/{subjectType}', [ApprovalFlowController::class, 'groupRemove'])->where('subjectType', '[a-z0-9_.]+');

    Route::get('company/approval-flows', [ApprovalFlowController::class, 'companyIndex']);
    Route::put('company/approval-flows/{subjectType}', [ApprovalFlowController::class, 'companySave'])->where('subjectType', '[a-z0-9_.]+');
    Route::delete('company/approval-flows/{subjectType}', [ApprovalFlowController::class, 'companyRemove'])->where('subjectType', '[a-z0-9_.]+');
});
