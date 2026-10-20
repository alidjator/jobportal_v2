<?php

use Illuminate\Support\Facades\Route;
use Src\JobSeeker\Infrastructure\JobSeekerServiceProvider;
use Src\JobSeeker\Interface\Http\Controllers\CandidateController;
use Src\JobSeeker\Interface\Http\Controllers\CvController;
use Src\JobSeeker\Interface\Http\Controllers\DataExportController;
use Src\JobSeeker\Interface\Http\Controllers\ImportSummaryController;
use Src\JobSeeker\Interface\Http\Controllers\MyProfileController;

// The job seeker's own profile and its lists; every use case checks that the caller is a job seeker.
Route::middleware('auth:user')->group(function (): void {
    Route::get('me/profile', [MyProfileController::class, 'show']);
    Route::patch('me/profile', [MyProfileController::class, 'update']);

    Route::put('me/skills', [MyProfileController::class, 'replaceSkills']);
    Route::put('me/languages', [MyProfileController::class, 'replaceLanguages']);
    Route::put('me/links', [MyProfileController::class, 'replaceLinks']);

    Route::get('me/experiences', [MyProfileController::class, 'experiences']);
    Route::post('me/experiences', [MyProfileController::class, 'addExperience']);
    Route::patch('me/experiences/{id}', [MyProfileController::class, 'changeExperience'])->whereNumber('id');
    Route::delete('me/experiences/{id}', [MyProfileController::class, 'deleteExperience'])->whereNumber('id');

    Route::get('me/educations', [MyProfileController::class, 'educations']);
    Route::post('me/educations', [MyProfileController::class, 'addEducation']);
    Route::patch('me/educations/{id}', [MyProfileController::class, 'changeEducation'])->whereNumber('id');
    Route::delete('me/educations/{id}', [MyProfileController::class, 'deleteEducation'])->whereNumber('id');

    Route::get('me/resumes', [MyProfileController::class, 'resumes']);
    Route::post('me/resumes', [MyProfileController::class, 'uploadResume']);
    Route::patch('me/resumes/{id}', [MyProfileController::class, 'renameResume'])->whereNumber('id');
    Route::delete('me/resumes/{id}', [MyProfileController::class, 'deleteResume'])->whereNumber('id');
    Route::get('me/resumes/{id}/download', [MyProfileController::class, 'downloadResume'])->whereNumber('id');

    // A copy of the job seeker's personal data (UU PDP): one request at a time, one a day; the archive lasts 24 hours.
    Route::post('me/data-export', [DataExportController::class, 'store']);
    Route::get('me/data-export', [DataExportController::class, 'show']);
    Route::get('me/data-export/download', [DataExportController::class, 'download']);

    // What the karir.pkp import carried for the account (`404 no-import-summary` for an account that was not imported).
    Route::get('me/import-summary', [ImportSummaryController::class, 'show']);
    Route::post('me/import-summary/dismiss', [ImportSummaryController::class, 'dismiss']);

    // The CV generator: the templates on offer, and a PDF made from the latest profile.
    Route::get('me/cv/templates', [CvController::class, 'templates']);
    Route::post('me/cv', [CvController::class, 'generate']);

    // The companies that opened the job seeker's profile or resumes.
    Route::get('me/profile/views', [CandidateController::class, 'views']);

    // The candidate search of company members; every use case checks the caller's company.
    Route::middleware('throttle:'.JobSeekerServiceProvider::CANDIDATE_SEARCH_LIMITER)->group(function (): void {
        Route::get('company/candidates', [CandidateController::class, 'index']);
        Route::get('company/jobs/{id}/candidates', [CandidateController::class, 'recommended'])->whereNumber('id');
        Route::get('company/candidates/{slug}', [CandidateController::class, 'show']);
        Route::get('company/candidates/{slug}/avatar', [CandidateController::class, 'avatar']);
        Route::get('company/candidates/{slug}/resumes/{id}', [CandidateController::class, 'resume'])->whereNumber('id');
    });
});
