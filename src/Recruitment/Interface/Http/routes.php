<?php

use Illuminate\Support\Facades\Route;
use Src\Recruitment\Interface\Http\Controllers\JobBoard\CandidateJobController;
use Src\Recruitment\Interface\Http\Controllers\JobBoard\PublicJobController;

// JobBoard: sisi publik/kandidat

Route::get('/home', [PublicJobController::class, 'home']);
Route::get('/jobs', [PublicJobController::class, 'index']);
Route::get('/jobs/{slug}', [PublicJobController::class, 'show']);

Route::middleware('auth:sanctum')->prefix('candidate')->group(function () {
    Route::get('/jobs', [CandidateJobController::class, 'index']);
    Route::get('/jobs/{slug}', [CandidateJobController::class, 'show']);
    Route::get('/applied-jobs', [CandidateJobController::class, 'applied']);
    Route::get('/favorite-jobs', [CandidateJobController::class, 'favorites']);
    Route::post('/jobs/{job}/bookmark', [CandidateJobController::class, 'bookmark'])->whereNumber('job');
    Route::post('/jobs/apply', [CandidateJobController::class, 'apply']);
    Route::get('/job-alert', [CandidateJobController::class, 'alerts']);
});
