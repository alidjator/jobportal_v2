<?php

use Illuminate\Support\Facades\Route;
use Src\Review\Infrastructure\ReviewServiceProvider;
use Src\Review\Interface\Http\Controllers\CompanyReviewsController;
use Src\Review\Interface\Http\Controllers\MyReviewsController;
use Src\Review\Interface\Http\Controllers\ReportController;
use Src\Shared\Interface\Http\Middleware\PublicCacheHeaders;

// Public: the published reviews of a company with the summary (a company the public cannot see is a 404).
Route::get('companies/{slug}/reviews', [CompanyReviewsController::class, 'publicIndex'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware(['throttle:'.ReviewServiceProvider::PUBLIC_LIMITER, PublicCacheHeaders::class.':public;max_age=60;etag']);

Route::middleware('auth:user')->group(function (): void {
    // A job seeker: write the one review of a company they applied to, manage their own, report someone else's. Writes are limited per account.
    Route::post('companies/{slug}/reviews', [MyReviewsController::class, 'store'])->where('slug', '[a-z0-9-]+')->middleware('throttle:'.ReviewServiceProvider::WRITE_LIMITER);
    Route::get('me/reviews', [MyReviewsController::class, 'index']);
    Route::get('me/reviews/{id}', [MyReviewsController::class, 'show'])->whereNumber('id');
    Route::put('me/reviews/{id}', [MyReviewsController::class, 'update'])->whereNumber('id')->middleware('throttle:'.ReviewServiceProvider::WRITE_LIMITER);
    Route::delete('me/reviews/{id}', [MyReviewsController::class, 'destroy'])->whereNumber('id');
    Route::post('reviews/{id}/report', [ReportController::class, 'store'])->whereNumber('id')->middleware('throttle:'.ReviewServiceProvider::WRITE_LIMITER);

    // The company: its reviews with its reply, the reply (company admins only, the use cases check it) and a report.
    Route::prefix('company')->group(function (): void {
        Route::get('reviews', [CompanyReviewsController::class, 'index']);
        Route::put('reviews/{id}/reply', [CompanyReviewsController::class, 'reply'])->whereNumber('id')->middleware('throttle:'.ReviewServiceProvider::COMPANY_WRITE_LIMITER);
        Route::delete('reviews/{id}/reply', [CompanyReviewsController::class, 'destroyReply'])->whereNumber('id');
        Route::post('reviews/{id}/report', [ReportController::class, 'store'])->whereNumber('id')->middleware('throttle:'.ReviewServiceProvider::COMPANY_WRITE_LIMITER);
    });
});
