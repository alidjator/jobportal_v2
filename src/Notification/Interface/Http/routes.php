<?php

use Illuminate\Support\Facades\Route;
use Src\Notification\Interface\Http\Controllers\DeviceTokenController;
use Src\Notification\Interface\Http\Controllers\NotificationController;
use Src\Notification\Interface\Http\Controllers\NotificationPreferenceController;

Route::middleware('auth:user')->group(function (): void {
    Route::get('me/notification-preferences', [NotificationPreferenceController::class, 'index']);
    Route::put('me/notification-preferences/{key}/{channel}', [NotificationPreferenceController::class, 'update']);
    Route::post('me/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('me/device-tokens/{token}', [DeviceTokenController::class, 'destroy']);
});

Route::middleware('auth:user')->prefix('me/notifications')->group(function (): void {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('read-all', [NotificationController::class, 'markAllRead']);
    Route::post('{id}/read', [NotificationController::class, 'markRead'])->whereUuid('id');
    Route::delete('{id}', [NotificationController::class, 'destroy'])->whereUuid('id');
});
