<?php

use Illuminate\Support\Facades\Route;
use Src\Newsletter\Infrastructure\NewsletterServiceProvider;
use Src\Newsletter\Interface\Http\Controllers\SubscriptionController;

// Public: join the list (always 202, whatever the address), confirm with the emailed token, leave. The unsubscribe token is the same in the page form and in the
// one-click address of the mail programs (`List-Unsubscribe-Post`).
Route::post('newsletter/subscribers', [SubscriptionController::class, 'subscribe'])->middleware('throttle:'.NewsletterServiceProvider::SUBSCRIBE_LIMITER);
Route::post('newsletter/confirm', [SubscriptionController::class, 'confirm'])->middleware('throttle:'.NewsletterServiceProvider::TOKEN_LIMITER);
Route::post('newsletter/unsubscribe', [SubscriptionController::class, 'unsubscribe'])->middleware('throttle:'.NewsletterServiceProvider::TOKEN_LIMITER);
Route::post('newsletter/unsubscribe/{token}', [SubscriptionController::class, 'unsubscribeOneClick'])
    ->where('token', '[0-9]+\.[0-9a-f]{64}')
    ->middleware('throttle:'.NewsletterServiceProvider::TOKEN_LIMITER);
