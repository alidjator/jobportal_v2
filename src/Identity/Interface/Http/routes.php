<?php

use Illuminate\Support\Facades\Route;
use Src\Identity\Interface\Http\Controllers\AuthController;

Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
Route::post('/social-media-authentication', [AuthController::class, 'socialAuthentication']);

Route::controller(AuthController::class)->group(function () {
    Route::post('/login', 'login');
    Route::post('/register', 'register');
    Route::get('/profile', 'profile')->middleware('auth:sanctum');
    Route::get('/user-info', 'userInfo');

    // Dibatasi laju (tidak ada di aplikasi lama) agar kode 6 digit tidak mudah ditebak.
    Route::post('/password/email', 'sendResetCodeEmail')->middleware('throttle:6,1');
    Route::post('/password/reset', 'reset')->middleware('throttle:10,1');
});
