<?php

use Illuminate\Support\Facades\Route;
use Src\Review\Application\ReviewPermissions;
use Src\Review\Interface\Http\Controllers\AdminReviewsController;

// Moderation of reviews, replies and reports, for admins with `reviews.moderate`.
Route::prefix('admin')->middleware(['auth:admin', 'can:'.ReviewPermissions::MODERATE])->group(function (): void {
    Route::get('reviews', [AdminReviewsController::class, 'index']);
    Route::get('reviews/{id}', [AdminReviewsController::class, 'show'])->whereNumber('id');
    Route::post('reviews/{id}/approve', [AdminReviewsController::class, 'approve'])->whereNumber('id');
    Route::post('reviews/{id}/reject', [AdminReviewsController::class, 'reject'])->whereNumber('id');
    Route::post('reviews/{id}/remove', [AdminReviewsController::class, 'remove'])->whereNumber('id');

    Route::get('review-replies', [AdminReviewsController::class, 'replies']);
    Route::post('review-replies/{reviewId}/approve', [AdminReviewsController::class, 'approveReply'])->whereNumber('reviewId');
    Route::post('review-replies/{reviewId}/reject', [AdminReviewsController::class, 'rejectReply'])->whereNumber('reviewId');
    Route::post('review-replies/{reviewId}/remove', [AdminReviewsController::class, 'removeReply'])->whereNumber('reviewId');

    Route::get('review-reports', [AdminReviewsController::class, 'reports']);
    Route::post('review-reports/{id}/dismiss', [AdminReviewsController::class, 'dismissReport'])->whereNumber('id');
});
