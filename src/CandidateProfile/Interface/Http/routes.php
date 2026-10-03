<?php

use Illuminate\Support\Facades\Route;
use Src\CandidateProfile\Interface\Http\Controllers\CandidateController;
use Src\CandidateProfile\Interface\Http\Controllers\CandidateDirectoryController;
use Src\CandidateProfile\Interface\Http\Middleware\EnsureCandidateRole;

Route::get('/candidates', [CandidateDirectoryController::class, 'index']);
Route::get('/candidates/{username}', [CandidateDirectoryController::class, 'show']);

Route::middleware('auth:sanctum')->prefix('candidate')->controller(CandidateController::class)->group(function () {
    // Daftar resume mengembalikan [] untuk non-kandidat, jadi tanpa EnsureCandidateRole.
    Route::get('/resumes', 'getResumes');

    Route::middleware(EnsureCandidateRole::class)->group(function () {
        Route::get('/', 'candidate');
        Route::get('/dashboard', 'dashboard');
        Route::get('/settings', 'fetchSettings');
        Route::post('/settings', 'updateSettings');
        Route::get('/resumes/{id}', 'getResumeById')->whereNumber('id');
        Route::post('/upload-resume', 'uploadResume');
        Route::post('/update-resume/{id}', 'updateResume')->whereNumber('id');
        Route::delete('/delete-resume/{id}', 'deleteResume')->whereNumber('id');
    });
});
