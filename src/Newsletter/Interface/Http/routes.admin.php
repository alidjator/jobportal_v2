<?php

use Illuminate\Support\Facades\Route;
use Src\Newsletter\Application\NewsletterPermissions;
use Src\Newsletter\Interface\Http\Controllers\IssueController;
use Src\Newsletter\Interface\Http\Controllers\SubscriberController;

// The list and the issues of the newsletter, for admins with `newsletter.manage`.
Route::prefix('admin/newsletter')->middleware(['auth:admin', 'can:'.NewsletterPermissions::NEWSLETTER_MANAGE])->group(function (): void {
    Route::get('subscribers', [SubscriberController::class, 'index']);
    Route::delete('subscribers/{id}', [SubscriberController::class, 'destroy'])->whereNumber('id');

    Route::get('issues', [IssueController::class, 'index']);
    Route::post('issues', [IssueController::class, 'store']);
    Route::get('issues/{id}', [IssueController::class, 'show'])->whereNumber('id');
    Route::put('issues/{id}', [IssueController::class, 'update'])->whereNumber('id');
    Route::delete('issues/{id}', [IssueController::class, 'destroy'])->whereNumber('id');
    Route::post('issues/{id}/test', [IssueController::class, 'test'])->whereNumber('id');
    Route::post('issues/{id}/send', [IssueController::class, 'send'])->whereNumber('id');
});
