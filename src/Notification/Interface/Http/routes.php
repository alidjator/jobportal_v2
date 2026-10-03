<?php

use Illuminate\Support\Facades\Route;
use Src\Notification\Interface\Http\Controllers\NotificationController;

Route::controller(NotificationController::class)->group(function () {
    Route::post('/store-token-anonymous-user', 'storeTokenAnonymous')->name('store.token.anonymous.user');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/notifications', 'index');
        Route::post('/store-token', 'storeToken')->name('store.token');
        Route::post('/send-notification', 'send');
        Route::post('/send-notification-all-users', 'sendToAll');
    });
});
