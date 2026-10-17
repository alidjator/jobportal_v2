<?php

use Illuminate\Support\Facades\Route;
use Src\Identity\Interface\Http\Middleware\RequireVerifiedEmail;
use Src\Recruitment\Infrastructure\RecruitmentServiceProvider;
use Src\Recruitment\Interface\Http\Controllers\ApplicationController;
use Src\Recruitment\Interface\Http\Controllers\ApplicationNoteController;
use Src\Recruitment\Interface\Http\Controllers\ApplicationRatingController;
use Src\Recruitment\Interface\Http\Controllers\BulkApplicationController;
use Src\Recruitment\Interface\Http\Controllers\CompanyApplicationController;
use Src\Recruitment\Interface\Http\Controllers\CompanyStageController;
use Src\Recruitment\Interface\Http\Controllers\EmailPreviewController;
use Src\Recruitment\Interface\Http\Controllers\InterviewController;
use Src\Recruitment\Interface\Http\Controllers\InterviewKitController;
use Src\Recruitment\Interface\Http\Controllers\InterviewPanelController;
use Src\Recruitment\Interface\Http\Controllers\InterviewQuestionController;
use Src\Recruitment\Interface\Http\Controllers\InterviewRecordingController;
use Src\Recruitment\Interface\Http\Controllers\JanusWebhookController;
use Src\Recruitment\Interface\Http\Controllers\JobInterviewKitController;
use Src\Recruitment\Interface\Http\Controllers\JobScreeningQuestionController;
use Src\Recruitment\Interface\Http\Controllers\MyInterviewController;
use Src\Recruitment\Interface\Http\Controllers\MyOfferController;
use Src\Recruitment\Interface\Http\Controllers\MyVideoInterviewController;
use Src\Recruitment\Interface\Http\Controllers\OfferController;
use Src\Recruitment\Interface\Http\Controllers\ReviewRequestController;
use Src\Recruitment\Interface\Http\Controllers\ScorecardController;
use Src\Recruitment\Interface\Http\Controllers\ScreeningQuestionController;
use Src\Recruitment\Interface\Http\Controllers\VideoInterviewController;
use Src\Recruitment\Interface\Http\Controllers\VoiceInterviewController;
use Src\Recruitment\Interface\Http\Middleware\VerifyJanusWebhook;
use Src\Shared\Interface\Http\RateLimit\ApiRateLimit;

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
    Route::get('company/jobs/{id}/applications/export', [CompanyApplicationController::class, 'export'])->whereNumber('id')->middleware('throttle:'.RecruitmentServiceProvider::EXPORT_LIMITER);
    Route::post('company/jobs/{id}/applications/bulk-move', [BulkApplicationController::class, 'move'])->whereNumber('id');
    Route::post('company/jobs/{id}/applications/bulk-reject', [BulkApplicationController::class, 'reject'])->whereNumber('id');
    Route::get('company/applications/{id}', [CompanyApplicationController::class, 'show'])->whereNumber('id');
    Route::get('company/applications/{id}/resume', [CompanyApplicationController::class, 'resume'])->whereNumber('id');
    Route::post('company/applications/{id}/move', [CompanyApplicationController::class, 'move'])->whereNumber('id');
    Route::post('company/applications/{id}/reject', [CompanyApplicationController::class, 'reject'])->whereNumber('id');
    Route::post('company/applications/{id}/withdraw', [CompanyApplicationController::class, 'withdraw'])->whereNumber('id');

    Route::get('company/applications/{id}/notes', [ApplicationNoteController::class, 'index'])->whereNumber('id');
    Route::post('company/applications/{id}/notes', [ApplicationNoteController::class, 'store'])->whereNumber('id');
    Route::put('company/applications/{id}/notes/{noteId}', [ApplicationNoteController::class, 'update'])->whereNumber(['id', 'noteId']);
    Route::delete('company/applications/{id}/notes/{noteId}', [ApplicationNoteController::class, 'destroy'])->whereNumber(['id', 'noteId']);
    Route::put('company/applications/{id}/rating', [ApplicationRatingController::class, 'update'])->whereNumber('id');
    Route::delete('company/applications/{id}/rating', [ApplicationRatingController::class, 'destroy'])->whereNumber('id');

    Route::post('company/jobs/{id}/review-requests', [ReviewRequestController::class, 'store'])->whereNumber('id');
    Route::get('company/jobs/{id}/review-requests', [ReviewRequestController::class, 'forJob'])->whereNumber('id');
    Route::get('company/review-requests', [ReviewRequestController::class, 'mine']);
    Route::get('company/review-requests/{id}', [ReviewRequestController::class, 'show'])->whereNumber('id');
    Route::put('company/review-requests/{id}/applications/{applicationId}/review', [ReviewRequestController::class, 'answer'])->whereNumber(['id', 'applicationId']);
    Route::post('company/review-requests/{id}/close', [ReviewRequestController::class, 'close'])->whereNumber('id');

    Route::get('company/applications/{id}/interviews', [InterviewController::class, 'index'])->whereNumber('id');
    Route::put('company/applications/{id}/interview', [InterviewController::class, 'store'])->whereNumber('id');
    Route::patch('company/applications/{id}/interview', [InterviewController::class, 'updateNote'])->whereNumber('id');
    Route::get('company/interviews', [InterviewController::class, 'mine']);
    Route::get('company/interviews/{id}/calendar.ics', [InterviewController::class, 'calendarFile'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/accept-proposal', [InterviewController::class, 'acceptProposal'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/cancel', [InterviewController::class, 'cancel'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/complete', [InterviewController::class, 'complete'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/video/join', [VideoInterviewController::class, 'join'])->whereNumber('id');
    Route::get('company/applications/{id}/interview/video/lobby', [VideoInterviewController::class, 'lobby'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/video/admit', [VideoInterviewController::class, 'admit'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/voice/join', [VoiceInterviewController::class, 'join'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/voice/call', [VoiceInterviewController::class, 'call'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/voice/hangup', [VoiceInterviewController::class, 'hangup'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/voice/recording/start', [VoiceInterviewController::class, 'startRecording'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/voice/recording/stop', [VoiceInterviewController::class, 'stopRecording'])->whereNumber('id');
    Route::get('company/interviews/{id}/calls', [VoiceInterviewController::class, 'calls'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/video/recording/start', [InterviewRecordingController::class, 'start'])->whereNumber('id');
    Route::post('company/applications/{id}/interview/video/recording/stop', [InterviewRecordingController::class, 'stop'])->whereNumber('id');
    Route::get('company/interviews/{id}/recordings', [InterviewRecordingController::class, 'index'])->whereNumber('id');
    Route::get('company/interviews/{id}/recordings/{recordingId}/file', [InterviewRecordingController::class, 'file'])->whereNumber(['id', 'recordingId']);
    Route::delete('company/interviews/{id}/recordings/{recordingId}', [InterviewRecordingController::class, 'destroy'])->whereNumber(['id', 'recordingId']);
    Route::get('company/interviews/{id}/attendance', [InterviewRecordingController::class, 'attendance'])->whereNumber('id');

    Route::get('company/interview-kits', [InterviewKitController::class, 'index']);
    Route::post('company/interview-kits', [InterviewKitController::class, 'store']);
    Route::get('company/interview-kits/{id}', [InterviewKitController::class, 'show'])->whereNumber('id');
    Route::put('company/interview-kits/{id}', [InterviewKitController::class, 'update'])->whereNumber('id');
    Route::delete('company/interview-kits/{id}', [InterviewKitController::class, 'destroy'])->whereNumber('id');

    Route::get('company/jobs/{id}/interview-kits', [JobInterviewKitController::class, 'index'])->whereNumber('id');
    Route::put('company/jobs/{id}/interview-kits/{stageId}', [JobInterviewKitController::class, 'update'])->whereNumber(['id', 'stageId']);
    Route::delete('company/jobs/{id}/interview-kits/{stageId}', [JobInterviewKitController::class, 'destroy'])->whereNumber(['id', 'stageId']);

    Route::get('company/interviews/{id}/questions', [InterviewQuestionController::class, 'show'])->whereNumber('id');
    Route::put('company/interviews/{id}/questions', [InterviewQuestionController::class, 'update'])->whereNumber('id');

    Route::put('company/interviews/{id}/participants', [InterviewPanelController::class, 'update'])->whereNumber('id');
    Route::post('company/interviews/{id}/rsvp', [InterviewPanelController::class, 'answer'])->whereNumber('id');

    Route::get('company/interviews/{id}/scorecard', [ScorecardController::class, 'show'])->whereNumber('id');
    Route::put('company/interviews/{id}/scorecard', [ScorecardController::class, 'update'])->whereNumber('id');
    Route::post('company/interviews/{id}/scorecard/submit', [ScorecardController::class, 'submit'])->whereNumber('id');
    Route::get('company/interviews/{id}/scorecards', [ScorecardController::class, 'index'])->whereNumber('id');
    Route::get('company/interviews/{id}/scorecards/{scorecardId}/revisions', [ScorecardController::class, 'revisions'])->whereNumber(['id', 'scorecardId']);

    Route::get('company/applications/{id}/offers', [OfferController::class, 'index'])->whereNumber('id');
    Route::put('company/applications/{id}/offer', [OfferController::class, 'store'])->whereNumber('id');
    // The email an interview, a test or an offer would send, shown before the recruiter sends it; nothing is saved or sent.
    Route::post('company/applications/{id}/email-preview', EmailPreviewController::class)->whereNumber('id');
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
    Route::post('me/applications/{id}/interview/video/join', [MyVideoInterviewController::class, 'join'])->whereNumber('id');
    Route::get('me/applications/{id}/interview/video/lobby', [MyVideoInterviewController::class, 'lobby'])->whereNumber('id');
    Route::post('me/applications/{id}/interview/recording-consent/withdraw', [MyInterviewController::class, 'withdrawRecordingConsent'])->whereNumber('id');

    Route::get('me/applications/{id}/offer', [MyOfferController::class, 'show'])->whereNumber('id');
    Route::post('me/applications/{id}/offer/accept', [MyOfferController::class, 'accept'])->whereNumber('id');
    Route::post('me/applications/{id}/offer/decline', [MyOfferController::class, 'decline'])->whereNumber('id');
});

// Internal webhooks of the video server (attendance events) and of its converter (finished recordings). They authenticate with a shared secret, come from one
// address on the Docker network (so the per-IP limit of the `api` group is lifted), and are not reachable from outside (`deploy/janus/`).
Route::middleware(VerifyJanusWebhook::class)->withoutMiddleware('throttle:'.ApiRateLimit::NAME)->prefix('internal/janus')->group(function (): void {
    Route::post('events', [JanusWebhookController::class, 'events']);
    Route::post('recordings', [JanusWebhookController::class, 'recordings']);
});
