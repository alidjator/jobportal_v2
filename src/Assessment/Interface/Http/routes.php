<?php

use Illuminate\Support\Facades\Route;
use Src\Assessment\Interface\Http\AuthoringRoutes;
use Src\Assessment\Interface\Http\Controllers\AttemptController;
use Src\Assessment\Interface\Http\Controllers\BatteryController;
use Src\Assessment\Interface\Http\Controllers\CatalogController;
use Src\Assessment\Interface\Http\Controllers\InvitationController;
use Src\Assessment\Interface\Http\Controllers\JobDefaultController;
use Src\Assessment\Interface\Http\Controllers\MyInvitationController;
use Src\Assessment\Interface\Http\Controllers\ProviderProfileController;
use Src\Assessment\Interface\Http\Controllers\PsychologistInvitationController;
use Src\Assessment\Interface\Http\Controllers\ResultController;
use Src\Assessment\Interface\Http\Controllers\ReviewController;
use Src\Assessment\Interface\Http\Controllers\VideoTakeController;
use Src\Assessment\Interface\Http\Middleware\ReadAttemptSession;
use Src\Identity\Interface\Http\Middleware\EnforceTermsAcceptance;

Route::prefix('provider')->group(function (): void {
    // The invitee has no account yet, so the token in the URL is the credential.
    Route::get('invitations/{token}', [PsychologistInvitationController::class, 'show']);
    Route::post('invitations/{token}/accept', [PsychologistInvitationController::class, 'accept']);

    // The consumer terms do not apply to psychologists; the use cases refuse every other kind of account.
    Route::middleware('auth:user')->withoutMiddleware(EnforceTermsAcceptance::class)->group(function (): void {
        Route::get('me', ProviderProfileController::class);

        AuthoringRoutes::register();

        Route::get('tests/{id}/norms', [BatteryController::class, 'norms'])->whereNumber('id');
        Route::put('tests/{id}/norms', [BatteryController::class, 'replaceNorms'])->whereNumber('id');
        Route::get('tests/{id}/report-template', [BatteryController::class, 'reportTemplate'])->whereNumber('id');
        Route::put('tests/{id}/report-template', [BatteryController::class, 'saveReportTemplate'])->whereNumber('id');
        Route::get('tests/{id}/explanation-template', [BatteryController::class, 'explanationTemplate'])->whereNumber('id');
        Route::put('tests/{id}/explanation-template', [BatteryController::class, 'saveExplanationTemplate'])->whereNumber('id');
        Route::put('tests/{id}/auto-issue', [BatteryController::class, 'autoIssue'])->whereNumber('id');

        // The review of the reports of the provider's batteries: the queue, the draft, the drawings, the edit and the signature.
        Route::get('reviews', [ReviewController::class, 'index']);
        Route::prefix('reviews/{id}')->where(['id' => '[0-9]+', 'questionId' => '[0-9]+'])->group(function (): void {
            Route::get('', [ReviewController::class, 'show']);
            Route::put('', [ReviewController::class, 'update']);
            Route::get('drawings/{questionId}', [ReviewController::class, 'drawing']);
            Route::put('drawings/{questionId}', [ReviewController::class, 'scoreDrawing']);
            Route::post('sign', [ReviewController::class, 'sign']);
            Route::post('mark-reviewed', [ReviewController::class, 'markReviewed']);
            Route::post('revise', [ReviewController::class, 'revise']);
        });
    });
});

// A company admin writes the company's skill tests; every member reads the catalogue. The use cases check the membership and the role.
Route::middleware('auth:user')->prefix('company/assessment')->group(function (): void {
    AuthoringRoutes::register();

    Route::get('library', [CatalogController::class, 'library']);
    Route::get('batteries', [CatalogController::class, 'batteries']);
    Route::get('sendable-tests', [CatalogController::class, 'sendable']);
    Route::get('catalog/{id}', [CatalogController::class, 'show'])->whereNumber('id');

    // The tests sent to the candidates, for the members who may see the application.
    Route::get('applications/{id}/invitations', [InvitationController::class, 'index'])->whereNumber('id');
    Route::put('applications/{id}/invitation', [InvitationController::class, 'store'])->whereNumber('id');
    Route::get('invitations', [ResultController::class, 'toScore']);
    Route::get('invitations/{id}', [InvitationController::class, 'show'])->whereNumber('id');
    Route::post('invitations/{id}/cancel', [InvitationController::class, 'cancel'])->whereNumber('id');
    Route::post('invitations/{id}/attachment', [InvitationController::class, 'attach'])->whereNumber('id');
    Route::delete('invitations/{id}/attachment', [InvitationController::class, 'detach'])->whereNumber('id');
    Route::get('invitations/{id}/attachment', [InvitationController::class, 'attachment'])->whereNumber('id');

    // The result of a test, its integrity log, the scoring of the written and video answers by the assessors, the video streams and the signed report as a PDF.
    Route::prefix('invitations/{id}')->where(['id' => '[0-9]+', 'questionId' => '[0-9]+'])->group(function (): void {
        Route::get('result', [ResultController::class, 'show']);
        Route::get('integrity-events', [ResultController::class, 'integrityEvents']);
        Route::get('scoring', [ResultController::class, 'scoring']);
        Route::put('scoring/questions/{questionId}', [ResultController::class, 'score']);
        Route::get('videos/{questionId}', [ResultController::class, 'video']);
        Route::get('report.pdf', [ResultController::class, 'report']);
    });
});

// The default test of a job per assessment stage, set in the job editor.
Route::middleware('auth:user')->group(function (): void {
    Route::get('company/jobs/{id}/assessments', [JobDefaultController::class, 'index'])->whereNumber('id');
    Route::get('company/jobs/{id}/assessors', [JobDefaultController::class, 'assessors'])->whereNumber('id');
    Route::put('company/jobs/{id}/assessments/{stageId}', [JobDefaultController::class, 'put'])->whereNumber(['id', 'stageId']);
    Route::delete('company/jobs/{id}/assessments/{stageId}', [JobDefaultController::class, 'destroy'])->whereNumber(['id', 'stageId']);
});

// The tests a job seeker was asked to take.
Route::middleware('auth:user')->prefix('me/assessment')->group(function (): void {
    Route::get('invitations', [MyInvitationController::class, 'index']);
    Route::get('invitations/{id}', [MyInvitationController::class, 'show'])->whereNumber('id');
    Route::get('invitations/{id}/explanation', [MyInvitationController::class, 'explanation'])->whereNumber('id');
    Route::post('invitations/{id}/decline', [MyInvitationController::class, 'decline'])->whereNumber('id');
    Route::get('invitations/{id}/attachment', [MyInvitationController::class, 'attachment'])->whereNumber('id');

    // Taking the test. The server keeps the time and the rules of every section.
    Route::prefix('invitations/{id}')->where(['id' => '[0-9]+', 'questionId' => '[0-9]+', 'column' => '[0-9]+', 'takeId' => '[0-9]+', 'index' => '[0-9]+'])->group(function (): void {
        Route::post('start', [AttemptController::class, 'start']);
        // Every other call carries the token of the device that holds the attempt (`X-Attempt-Session`, issued by `start`).
        Route::middleware(ReadAttemptSession::class)->group(function (): void {
            Route::get('attempt', [AttemptController::class, 'show']);
            Route::put('attempt/questions/{questionId}/answer', [AttemptController::class, 'saveAnswer']);
            Route::delete('attempt/questions/{questionId}/answer', [AttemptController::class, 'clearAnswer']);
            Route::post('attempt/questions/{questionId}/drawing', [AttemptController::class, 'uploadDrawing']);
            Route::get('attempt/questions/{questionId}/video', [VideoTakeController::class, 'show']);
            Route::post('attempt/questions/{questionId}/video/takes', [VideoTakeController::class, 'start']);
            Route::put('attempt/questions/{questionId}/video/takes/{takeId}/chunks/{index}', [VideoTakeController::class, 'chunk']);
            Route::post('attempt/questions/{questionId}/video/takes/{takeId}/complete', [VideoTakeController::class, 'complete']);
            Route::put('attempt/cursor', [AttemptController::class, 'move']);
            Route::post('attempt/next-section', [AttemptController::class, 'finishSection']);
            Route::put('attempt/columns/{column}', [AttemptController::class, 'saveColumn']);
            Route::post('attempt/integrity-events', [AttemptController::class, 'reportEvent']);
            Route::post('attempt/submit', [AttemptController::class, 'submit']);
        });
    });
});
