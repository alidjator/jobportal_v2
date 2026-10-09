<?php

use Illuminate\Support\Facades\Route;
use Src\Campaign\Infrastructure\CampaignServiceProvider;
use Src\Campaign\Interface\Http\Controllers\CampaignController;
use Src\Campaign\Interface\Http\Controllers\PublicCampaignController;
use Src\Shared\Interface\Http\Middleware\PublicCacheHeaders;

// Public: a running campaign by its code, for the registration page (limited per client address, cached like the public job page). A campaign that is off, not
// started or over is a 404.
Route::get('campaigns/{code}', [PublicCampaignController::class, 'show'])
    ->where('code', '[A-Za-z0-9_.-]{1,64}')
    ->middleware(['throttle:'.CampaignServiceProvider::PUBLIC_LIMITER, PublicCacheHeaders::class.':public;max_age=60;etag']);

// The campaigns of the caller's own company; only a company admin of an active company may use them (the use cases check it).
Route::middleware('auth:user')->prefix('company')->group(function (): void {
    Route::get('campaigns', [CampaignController::class, 'index']);
    Route::post('campaigns', [CampaignController::class, 'store']);
    Route::get('campaigns/{id}', [CampaignController::class, 'show'])->whereNumber('id');
    Route::put('campaigns/{id}', [CampaignController::class, 'update'])->whereNumber('id');
    Route::delete('campaigns/{id}', [CampaignController::class, 'destroy'])->whereNumber('id');
    Route::get('campaigns/{id}/results', [CampaignController::class, 'results'])->whereNumber('id');
    Route::get('campaigns/{id}/qr', [CampaignController::class, 'qr'])->whereNumber('id');
});
