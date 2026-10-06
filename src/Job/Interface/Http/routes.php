<?php

use Illuminate\Support\Facades\Route;
use Src\Job\Interface\Http\Controllers\CompanyJobController;

// The jobs of the caller's own company; every use case checks that the caller is a member of an active company and may see the job.
Route::middleware('auth:user')->group(function (): void {
    Route::get('company/jobs', [CompanyJobController::class, 'index']);
    Route::post('company/jobs', [CompanyJobController::class, 'store']);
    Route::get('company/jobs/{id}', [CompanyJobController::class, 'show'])->whereNumber('id');
    Route::patch('company/jobs/{id}', [CompanyJobController::class, 'update'])->whereNumber('id');
    Route::delete('company/jobs/{id}', [CompanyJobController::class, 'destroy'])->whereNumber('id');
    Route::get('company/jobs/{id}/assignees', [CompanyJobController::class, 'assignees'])->whereNumber('id');
    Route::put('company/jobs/{id}/assignees', [CompanyJobController::class, 'replaceAssignees'])->whereNumber('id');
});
