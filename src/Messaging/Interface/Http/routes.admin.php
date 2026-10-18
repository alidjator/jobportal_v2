<?php

use Illuminate\Support\Facades\Route;
use Src\Messaging\Application\MessagingPermissions;
use Src\Messaging\Interface\Http\Controllers\AdminMessageReportController;

Route::prefix('admin')->middleware(['auth:admin', 'can:'.MessagingPermissions::MESSAGES_MODERATE])->group(function (): void {
    Route::get('message-reports', [AdminMessageReportController::class, 'index']);
    Route::get('message-reports/{id}', [AdminMessageReportController::class, 'show'])->whereNumber('id');
    Route::post('message-reports/{id}/dismiss', [AdminMessageReportController::class, 'dismiss'])->whereNumber('id');
    Route::post('message-reports/{id}/action', [AdminMessageReportController::class, 'action'])->whereNumber('id');
});
