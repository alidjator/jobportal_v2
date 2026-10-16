<?php

use Illuminate\Support\Facades\Route;
use Src\Job\Infrastructure\JobServiceProvider;
use Src\Job\Interface\Http\Controllers\AlertJobController;
use Src\Job\Interface\Http\Controllers\CompanyJobController;
use Src\Job\Interface\Http\Controllers\JobReportController;
use Src\Job\Interface\Http\Controllers\PublicJobController;
use Src\Job\Interface\Http\Controllers\RecommendationController;
use Src\Job\Interface\Http\Controllers\SalaryInsightController;
use Src\Job\Interface\Http\Controllers\SavedJobController;
use Src\Shared\Interface\Http\Middleware\PublicCacheHeaders;

// Public: the search over live jobs (limited per client address, cached like the page below). A job seeker who sends a token also gets the match with their
// profile, and then the answer is private (see `PublicCacheHeaders`).
Route::get('jobs', [PublicJobController::class, 'index'])->middleware(PublicCacheHeaders::class.':public;max_age=60;etag');

// Public: the page of a live job. A closed or taken-down job disappears within a minute (the cache lifetime); clients revalidate with the ETag.
Route::get('jobs/{job}', [PublicJobController::class, 'show'])
    ->where('job', '[0-9]+(?:-[a-z0-9-]*)?')
    ->middleware(PublicCacheHeaders::class.':public;max_age=60;etag');

// Public: the jobs like this one (live, closed or expired job); the answer may be cached for 10 minutes.
Route::get('jobs/{job}/similar', [PublicJobController::class, 'similar'])
    ->where('job', '[0-9]+(?:-[a-z0-9-]*)?')
    ->middleware(PublicCacheHeaders::class.':public;max_age=600;etag');

// Public: one counted view of a live job, sent by the client when a person looks at the page (the page itself is cached and only reads). Limited per client address.
Route::post('jobs/{job}/view', [PublicJobController::class, 'view'])
    ->where('job', '[0-9]+(?:-[a-z0-9-]*)?')
    ->middleware('throttle:'.JobServiceProvider::VIEW_LIMITER);

// A job seeker with a verified email reports a live job (10 an hour); enough reports put it on hold until an admin decides.
Route::post('jobs/{id}/reports', [JobReportController::class, 'store'])->whereNumber('id')->middleware(['auth:user', 'throttle:'.JobServiceProvider::REPORT_LIMITER]);

// Public: what the jobs on this platform pay for a role (J10a): a median and a spread, widened when the group is too small. Limited per client address, cached for 5 minutes.
Route::get('salary-insight', [SalaryInsightController::class, 'show'])
    ->middleware(['throttle:'.JobServiceProvider::INSIGHT_LIMITER, PublicCacheHeaders::class.':public;max_age=300;etag']);

// The live jobs one of the job seeker's own alerts matches right now (the alert itself is managed by the JobAlert context).
Route::get('me/job-alerts/{id}/jobs', [AlertJobController::class, 'index'])->whereNumber('id')->middleware('auth:user');

// The live jobs that fit the job seeker best (J8).
Route::get('me/recommendations', [RecommendationController::class, 'index'])->middleware('auth:user');

// The jobs the job seeker saved: save and remove are idempotent, the list keeps a job that is no longer live (as an entry without a row).
Route::middleware('auth:user')->group(function (): void {
    Route::get('me/saved-jobs', [SavedJobController::class, 'index']);
    Route::put('me/saved-jobs/{id}', [SavedJobController::class, 'save'])->whereNumber('id')->middleware('throttle:'.JobServiceProvider::SAVED_LIMITER);
    Route::delete('me/saved-jobs/{id}', [SavedJobController::class, 'remove'])->whereNumber('id')->middleware('throttle:'.JobServiceProvider::SAVED_LIMITER);
});

// The jobs of the caller's own company; every use case checks that the caller is a member of an active company and may see the job.
Route::middleware('auth:user')->group(function (): void {
    Route::get('company/jobs', [CompanyJobController::class, 'index']);
    Route::post('company/jobs', [CompanyJobController::class, 'store']);
    Route::get('company/jobs/{id}', [CompanyJobController::class, 'show'])->whereNumber('id');
    Route::get('company/jobs/{id}/performance', [CompanyJobController::class, 'performance'])->whereNumber('id');
    Route::patch('company/jobs/{id}', [CompanyJobController::class, 'update'])->whereNumber('id');
    Route::delete('company/jobs/{id}', [CompanyJobController::class, 'destroy'])->whereNumber('id');
    Route::post('company/jobs/{id}/publish', [CompanyJobController::class, 'publish'])->whereNumber('id');
    Route::post('company/jobs/{id}/close', [CompanyJobController::class, 'close'])->whereNumber('id');
    Route::post('company/jobs/{id}/reopen', [CompanyJobController::class, 'reopen'])->whereNumber('id');
    Route::post('company/jobs/{id}/clone', [CompanyJobController::class, 'clone'])->whereNumber('id');
    Route::get('company/jobs/{id}/assignees', [CompanyJobController::class, 'assignees'])->whereNumber('id');
    Route::put('company/jobs/{id}/assignees', [CompanyJobController::class, 'replaceAssignees'])->whereNumber('id');
    Route::put('company/jobs/{id}/hiring-manager-boundary', [CompanyJobController::class, 'setHiringManagerBoundary'])->whereNumber('id');
});
