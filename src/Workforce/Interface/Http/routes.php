<?php

use Illuminate\Support\Facades\Route;
use Src\Workforce\Interface\Http\Controllers\PlanController;

// The manpower plans of the signed-in member's tenant (`workforce.plans.view` or `.manage` reads, `.manage` changes) and of the group (`group.workforce.view` or `.manage`
// reads, `.manage` changes). `PUT` replaces the lines of a draft or rejected plan; `revise` starts a new version from the one in force.
Route::middleware('auth:user')->group(function (): void {
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
});
