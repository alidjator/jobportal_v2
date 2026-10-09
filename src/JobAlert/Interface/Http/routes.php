<?php

use Illuminate\Support\Facades\Route;
use Src\JobAlert\Interface\Http\Controllers\JobAlertController;

// The job seeker's job alerts: saved searches that tell them about new jobs. The live jobs an alert matches right now are `GET me/job-alerts/{id}/jobs`, served by Job.
Route::middleware('auth:user')->group(function (): void {
    Route::get('me/job-alerts', [JobAlertController::class, 'index']);
    Route::post('me/job-alerts', [JobAlertController::class, 'store']);
    Route::get('me/job-alerts/{id}', [JobAlertController::class, 'show'])->whereNumber('id');
    Route::put('me/job-alerts/{id}', [JobAlertController::class, 'update'])->whereNumber('id');
    Route::delete('me/job-alerts/{id}', [JobAlertController::class, 'destroy'])->whereNumber('id');
    Route::post('me/job-alerts/{id}/pause', [JobAlertController::class, 'pause'])->whereNumber('id');
    Route::post('me/job-alerts/{id}/resume', [JobAlertController::class, 'resume'])->whereNumber('id');
});
