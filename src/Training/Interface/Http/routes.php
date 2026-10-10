<?php

use Illuminate\Support\Facades\Route;
use Src\Shared\Interface\Http\Middleware\PublicCacheHeaders;
use Src\Training\Infrastructure\TrainingServiceProvider;
use Src\Training\Interface\Http\Controllers\TrainingController;

// Public: the course catalog of the training platform (limited per client address). It holds no personal data.
Route::get('training/courses', [TrainingController::class, 'courses'])
    ->middleware(['throttle:'.TrainingServiceProvider::PUBLIC_LIMITER, PublicCacheHeaders::class.':public;max_age=60;etag']);

// The courses the signed-in job seeker is enrolled in, with their progress (needs a verified email; the use case checks it).
Route::get('me/training/courses', [TrainingController::class, 'mine'])->middleware('auth:user');
