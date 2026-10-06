<?php

use Illuminate\Support\Facades\Route;
use Src\Job\Interface\Http\Controllers\CompanyJobController;
use Src\Job\Interface\Http\Controllers\PublicJobController;

// Public: the search over live jobs (limited per client address, cached like the page below).
Route::get('jobs', [PublicJobController::class, 'index'])->middleware('cache.headers:public;max_age=60;etag');

// Public: the page of a live job. A closed or taken-down job disappears within a minute (the cache lifetime); clients revalidate with the ETag.
Route::get('jobs/{job}', [PublicJobController::class, 'show'])
    ->where('job', '[0-9]+(?:-[a-z0-9-]*)?')
    ->middleware('cache.headers:public;max_age=60;etag');

// The jobs of the caller's own company; every use case checks that the caller is a member of an active company and may see the job.
Route::middleware('auth:user')->group(function (): void {
    Route::get('company/jobs', [CompanyJobController::class, 'index']);
    Route::post('company/jobs', [CompanyJobController::class, 'store']);
    Route::get('company/jobs/{id}', [CompanyJobController::class, 'show'])->whereNumber('id');
    Route::patch('company/jobs/{id}', [CompanyJobController::class, 'update'])->whereNumber('id');
    Route::delete('company/jobs/{id}', [CompanyJobController::class, 'destroy'])->whereNumber('id');
    Route::post('company/jobs/{id}/publish', [CompanyJobController::class, 'publish'])->whereNumber('id');
    Route::post('company/jobs/{id}/close', [CompanyJobController::class, 'close'])->whereNumber('id');
    Route::post('company/jobs/{id}/reopen', [CompanyJobController::class, 'reopen'])->whereNumber('id');
    Route::post('company/jobs/{id}/clone', [CompanyJobController::class, 'clone'])->whereNumber('id');
    Route::get('company/jobs/{id}/assignees', [CompanyJobController::class, 'assignees'])->whereNumber('id');
    Route::put('company/jobs/{id}/assignees', [CompanyJobController::class, 'replaceAssignees'])->whereNumber('id');
});
