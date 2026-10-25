<?php

use Illuminate\Support\Facades\Route;
use Src\Platform\Interface\Http\Controllers\EmailWebhookController;
use Src\Platform\Interface\Http\Controllers\PortalController;
use Src\Platform\Interface\Http\Controllers\SchedulerHealthController;

// Answers 404 until an email provider's parser is registered (`EmailEventParsers`).
Route::post('webhooks/email/{provider}', EmailWebhookController::class)->where('provider', '[a-z0-9_-]{1,40}');

// For an outside uptime monitor: 200 while the scheduler's heartbeat is less than 5 minutes old, else 503.
Route::get('health/scheduler', SchedulerHealthController::class);

// Who the career site is for and how it looks; a visitor of the site reads it on every page (a minute of public cache).
Route::get('portal', [PortalController::class, 'show']);
