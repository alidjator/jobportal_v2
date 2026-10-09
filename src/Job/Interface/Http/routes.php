<?php

use Illuminate\Support\Facades\Route;
use Src\Job\Interface\Http\Controllers\AlertJobController;
use Src\Job\Interface\Http\Controllers\CompanyJobController;
use Src\Job\Interface\Http\Controllers\PublicJobController;
use Src\Job\Interface\Http\Controllers\RecommendationController;
use Src\Shared\Interface\Http\Middleware\PublicCacheHeaders;

// Public: the search over live jobs (limited per client address, cached like the page below). A job seeker who sends a token also gets the match with their
// profile, and then the answer is private (see `PublicCacheHeaders`).
Route::get('jobs', [PublicJobController::class, 'index'])->middleware(PublicCacheHeaders::class.':public;max_age=60;etag');

// Public: the page of a live job. A closed or taken-down job disappears within a minute (the cache lifetime); clients revalidate with the ETag.
Route::get('jobs/{job}', [PublicJobController::class, 'show'])
    ->where('job', '[0-9]+(?:-[a-z0-9-]*)?')
    ->middleware(PublicCacheHeaders::class.':public;max_age=60;etag');

// The live jobs one of the job seeker's own alerts matches right now (the alert itself is managed by the JobAlert context).
Route::get('me/job-alerts/{id}/jobs', [AlertJobController::class, 'index'])->whereNumber('id')->middleware('auth:user');

// The live jobs that fit the job seeker best (J8).
Route::get('me/recommendations', [RecommendationController::class, 'index'])->middleware('auth:user');

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
