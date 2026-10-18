<?php

use Illuminate\Support\Facades\Route;
use Src\Identity\Interface\Http\Middleware\RequireVerifiedEmail;
use Src\Messaging\Infrastructure\MessagingServiceProvider;
use Src\Messaging\Interface\Http\Controllers\BulkMessageController;
use Src\Messaging\Interface\Http\Controllers\ConversationController;
use Src\Messaging\Interface\Http\Controllers\MessageController;
use Src\Messaging\Interface\Http\Controllers\MessageReportController;

// Chat. Every use case checks the side of the caller and what they may see; sending affects another party, so it needs a verified email and is throttled.
Route::middleware('auth:user')->group(function (): void {
    $report = ['throttle:'.MessagingServiceProvider::REPORT_LIMITER];
    $send = [RequireVerifiedEmail::ALIAS, 'throttle:'.MessagingServiceProvider::SEND_LIMITER];

    // The company side: a member sees the conversations of job seekers who applied to their jobs, and the ones they started; a company admin sees all.
    Route::get('company/conversations', [ConversationController::class, 'index']);
    Route::post('company/conversations', [ConversationController::class, 'startAsCompany'])->middleware($send);
    Route::get('company/conversations/unread-count', [ConversationController::class, 'unreadCount']);
    Route::get('company/conversations/{id}', [ConversationController::class, 'show'])->whereNumber('id');
    Route::post('company/conversations/{id}/read', [ConversationController::class, 'read'])->whereNumber('id');
    Route::post('company/conversations/{id}/close', [ConversationController::class, 'close'])->whereNumber('id');
    Route::post('company/conversations/{id}/reopen', [ConversationController::class, 'reopen'])->whereNumber('id');
    Route::post('company/conversations/{id}/report', [MessageReportController::class, 'store'])->whereNumber('id')->middleware($report);
    Route::get('company/conversations/{id}/messages', [MessageController::class, 'index'])->whereNumber('id');
    Route::post('company/conversations/{id}/messages', [MessageController::class, 'store'])->whereNumber('id')->middleware($send);
    Route::delete('company/messages/{id}', [MessageController::class, 'destroy'])->whereNumber('id');
    Route::get('company/messages/{id}/attachments/{attachmentId}', [MessageController::class, 'attachment'])->whereNumber(['id', 'attachmentId']);
    Route::post('company/jobs/{id}/applications/bulk-message', [BulkMessageController::class, 'store'])->whereNumber('id')->middleware($send);

    // The job seeker's side.
    Route::get('me/conversations', [ConversationController::class, 'index']);
    Route::post('me/conversations', [ConversationController::class, 'startAsSeeker'])->middleware($send);
    Route::get('me/conversations/unread-count', [ConversationController::class, 'unreadCount']);
    Route::get('me/conversations/{id}', [ConversationController::class, 'show'])->whereNumber('id');
    Route::post('me/conversations/{id}/read', [ConversationController::class, 'read'])->whereNumber('id');
    Route::post('me/conversations/{id}/report', [MessageReportController::class, 'store'])->whereNumber('id')->middleware($report);
    Route::post('me/conversations/{id}/block', [ConversationController::class, 'block'])->whereNumber('id');
    Route::delete('me/conversations/{id}/block', [ConversationController::class, 'unblock'])->whereNumber('id');
    Route::get('me/conversations/{id}/messages', [MessageController::class, 'index'])->whereNumber('id');
    Route::post('me/conversations/{id}/messages', [MessageController::class, 'store'])->whereNumber('id')->middleware($send);
    Route::delete('me/messages/{id}', [MessageController::class, 'destroy'])->whereNumber('id');
    Route::get('me/messages/{id}/attachments/{attachmentId}', [MessageController::class, 'attachment'])->whereNumber(['id', 'attachmentId']);
});
