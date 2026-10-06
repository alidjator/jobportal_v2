<?php

use Illuminate\Support\Facades\Route;
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
});
