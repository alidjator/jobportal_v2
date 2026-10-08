<?php

use Illuminate\Support\Facades\Route;
use Src\Identity\Interface\Http\Middleware\RequireVerifiedEmail;
use Src\Recruitment\Interface\Http\Controllers\ApplicationController;
use Src\Recruitment\Interface\Http\Controllers\ApplicationNoteController;
use Src\Recruitment\Interface\Http\Controllers\ApplicationRatingController;
use Src\Recruitment\Interface\Http\Controllers\CompanyApplicationController;
use Src\Recruitment\Interface\Http\Controllers\CompanyStageController;
use Src\Recruitment\Interface\Http\Controllers\InterviewController;
use Src\Recruitment\Interface\Http\Controllers\JobScreeningQuestionController;
use Src\Recruitment\Interface\Http\Controllers\MyInterviewController;
use Src\Recruitment\Interface\Http\Controllers\MyOfferController;
use Src\Recruitment\Interface\Http\Controllers\OfferController;
use Src\Recruitment\Interface\Http\Controllers\ScreeningQuestionController;

// Public: what an applicant answers for a live job. A job that is not live (or whose company is hidden) is the same 404 as on the job page; clients revalidate with the ETag.
Route::get('jobs/{job}/screening-questions', [JobScreeningQuestionController::class, 'publicIndex'])
    ->where('job', '[0-9]+(?:-[a-z0-9-]*)?')
    ->middleware('cache.headers:public;max_age=60;etag');

// The stages of the caller's own company; every use case checks that the caller is a member of an active company (and a company admin for changes).
Route::middleware('auth:user')->group(function (): void {
    Route::get('company/stages', [CompanyStageController::class, 'index']);
    Route::post('company/stages', [CompanyStageController::class, 'store']);
    Route::put('company/stages/order', [CompanyStageController::class, 'reorder']);
    Route::patch('company/stages/{id}', [CompanyStageController::class, 'update'])->whereNumber('id');
    Route::delete('company/stages/{id}', [CompanyStageController::class, 'destroy'])->whereNumber('id');

    Route::get('company/screening-questions', [ScreeningQuestionController::class, 'index']);
    Route::post('company/screening-questions', [ScreeningQuestionController::class, 'store']);
    Route::get('company/screening-questions/{id}', [ScreeningQuestionController::class, 'show'])->whereNumber('id');
    Route::patch('company/screening-questions/{id}', [ScreeningQuestionController::class, 'update'])->whereNumber('id');
    Route::delete('company/screening-questions/{id}', [ScreeningQuestionController::class, 'destroy'])->whereNumber('id');
    Route::post('company/screening-questions/{id}/archive', [ScreeningQuestionController::class, 'archive'])->whereNumber('id');
    Route::post('company/screening-questions/{id}/restore', [ScreeningQuestionController::class, 'restore'])->whereNumber('id');

    Route::get('company/jobs/{id}/screening-questions', [JobScreeningQuestionController::class, 'index'])->whereNumber('id');
    Route::put('company/jobs/{id}/screening-questions', [JobScreeningQuestionController::class, 'replace'])->whereNumber('id');

    Route::get('company/jobs/{id}/applications', [CompanyApplicationController::class, 'index'])->whereNumber('id');
    Route::get('company/applications/{id}', [CompanyApplicationController::class, 'show'])->whereNumber('id');
    Route::get('company/applications/{id}/resume', [CompanyApplicationController::class, 'resume'])->whereNumber('id');
    Route::post('company/applications/{id}/move', [CompanyApplicationController::class, 'move'])->whereNumber('id');
    Route::post('company/applications/{id}/reject', [CompanyApplicationController::class, 'reject'])->whereNumber('id');

    Route::get('company/applications/{id}/notes', [ApplicationNoteController::class, 'index'])->whereNumber('id');
    Route::post('company/applications/{id}/notes', [ApplicationNoteController::class, 'store'])->whereNumber('id');
    Route::put('company/applications/{id}/notes/{noteId}', [ApplicationNoteController::class, 'update'])->whereNumber(['id', 'noteId']);
    Route::delete('company/applications/{id}/notes/{noteId}', [ApplicationNoteController::class, 'destroy'])->whereNumber(['id', 'noteId']);
    Route::put('company/applications/{id}/rating', [ApplicationRatingController::class, 'update'])->whereNumber('id');
    Route::delete('company/applications/{id}/rating', [ApplicationRatingController::class, 'destroy'])->whereNumber('id');

    Route::get('company/applications/{id}/interviews', [InterviewController::class, 'index'])->whereNumber('id');
    Route::put('company/applications/{id}/interview', [InterviewController::class, 'store'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/accept-proposal', [InterviewController::class, 'acceptProposal'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/cancel', [InterviewController::class, 'cancel'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/complete', [InterviewController::class, 'complete'])->whereNumber('id');

    Route::get('company/applications/{id}/offers', [OfferController::class, 'index'])->whereNumber('id');
    Route::put('company/applications/{id}/offer', [OfferController::class, 'store'])->whereNumber('id');
    Route::post('company/applications/{id}/offer/withdraw', [OfferController::class, 'withdraw'])->whereNumber('id');
});

// A job seeker applies to a live job. Applying affects another party, so the email must be verified.
Route::post('jobs/{job}/applications', [ApplicationController::class, 'store'])
    ->where('job', '[0-9]+(?:-[a-z0-9-]*)?')
    ->middleware(['auth:user', RequireVerifiedEmail::ALIAS]);

// The applicant's own applications.
Route::middleware('auth:user')->group(function (): void {
    Route::get('me/applications', [ApplicationController::class, 'index']);
    Route::get('me/applications/{id}', [ApplicationController::class, 'show'])->whereNumber('id');
    Route::get('me/applications/{id}/resume', [ApplicationController::class, 'resume'])->whereNumber('id');
    Route::post('me/applications/{id}/withdraw', [ApplicationController::class, 'withdraw'])->whereNumber('id');

    Route::get('me/applications/{id}/interview', [MyInterviewController::class, 'show'])->whereNumber('id');
    Route::post('me/applications/{id}/interview/confirm', [MyInterviewController::class, 'confirm'])->whereNumber('id');
    Route::post('me/applications/{id}/interview/decline', [MyInterviewController::class, 'decline'])->whereNumber('id');
    Route::post('me/applications/{id}/interview/propose', [MyInterviewController::class, 'propose'])->whereNumber('id');

    Route::get('me/applications/{id}/offer', [MyOfferController::class, 'show'])->whereNumber('id');
    Route::post('me/applications/{id}/offer/accept', [MyOfferController::class, 'accept'])->whereNumber('id');
    Route::post('me/applications/{id}/offer/decline', [MyOfferController::class, 'decline'])->whereNumber('id');
});
