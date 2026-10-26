<?php

use Illuminate\Support\Facades\Route;
use Src\Workforce\Interface\Http\Controllers\PlanController;
use Src\Workforce\Interface\Http\Controllers\RequisitionController;

// The manpower plans of the signed-in member's tenant (`workforce.plans.view` or `.manage` reads, `.manage` changes) and of the group (`group.workforce.view` or `.manage`
// reads, `.manage` changes). `PUT` replaces the lines of a draft or rejected plan; `revise` starts a new version from the one in force.
Route::middleware('auth:user')->group(function (): void {
    // The choices of the plan and requisition forms for a member of an internal tenant (`workforce.plans.manage` or `workforce.requisitions.create`), read only.
    Route::get('company/workforce/options', [PlanController::class, 'options']);
    Route::get('company/workforce/plans', [PlanController::class, 'companyIndex']);
    Route::post('company/workforce/plans', [PlanController::class, 'companyStore']);
    Route::get('company/workforce/plans/{id}', [PlanController::class, 'companyShow'])->whereNumber('id');
    Route::put('company/workforce/plans/{id}', [PlanController::class, 'companyUpdate'])->whereNumber('id');
    Route::post('company/workforce/plans/{id}/submit', [PlanController::class, 'companySubmit'])->whereNumber('id');
    Route::post('company/workforce/plans/{id}/revise', [PlanController::class, 'companyRevise'])->whereNumber('id');

    Route::get('organization/workforce/plans', [PlanController::class, 'groupIndex']);
    Route::post('organization/workforce/plans', [PlanController::class, 'groupStore']);
    Route::get('organization/workforce/plans/{id}', [PlanController::class, 'groupShow'])->whereNumber('id');
    Route::put('organization/workforce/plans/{id}', [PlanController::class, 'groupUpdate'])->whereNumber('id');
    Route::post('organization/workforce/plans/{id}/submit', [PlanController::class, 'groupSubmit'])->whereNumber('id');
    Route::post('organization/workforce/plans/{id}/revise', [PlanController::class, 'groupRevise'])->whereNumber('id');

    // Requisitions: `workforce.requisitions.view` or `.create` reads, `.create` drafts, submits, amends, closes and cancels; the group reads them with `group.workforce.view`.
    // `PUT` replaces what a draft or rejected requisition says; `jobs` opens a draft job for an approved one.
    Route::get('company/requisitions', [RequisitionController::class, 'companyIndex']);
    Route::post('company/requisitions', [RequisitionController::class, 'store']);
    Route::get('company/requisitions/{id}', [RequisitionController::class, 'companyShow'])->whereNumber('id');
    Route::put('company/requisitions/{id}', [RequisitionController::class, 'update'])->whereNumber('id');
    Route::post('company/requisitions/{id}/submit', [RequisitionController::class, 'submit'])->whereNumber('id');
    Route::post('company/requisitions/{id}/amend', [RequisitionController::class, 'amend'])->whereNumber('id');
    Route::post('company/requisitions/{id}/close', [RequisitionController::class, 'close'])->whereNumber('id');
    Route::post('company/requisitions/{id}/cancel', [RequisitionController::class, 'cancel'])->whereNumber('id');
    Route::post('company/requisitions/{id}/jobs', [RequisitionController::class, 'openJob'])->whereNumber('id');

    Route::get('organization/requisitions', [RequisitionController::class, 'groupIndex']);
    Route::get('organization/requisitions/{id}', [RequisitionController::class, 'groupShow'])->whereNumber('id');
});
