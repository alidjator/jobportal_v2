<?php

use Illuminate\Support\Facades\Route;
use Src\Content\Interface\Http\Controllers\ContentController;

Route::controller(ContentController::class)->group(function () {
    Route::get('/about', 'about');
    Route::get('/posts', 'posts');
    Route::get('/post/{slug}', 'postDetails');
    Route::post('/comment/{slug}/store', 'comment')->middleware('auth:sanctum');
    Route::get('/faq', 'faq');
    Route::get('/terms-condition', 'termsCondition');
    Route::get('/privacy-policy', 'privacyPolicy');
    Route::get('/refund-policy', 'refundPolicy');
    Route::post('/contact', 'contact');
    Route::get('/page-list', 'pageList');
});
