<?php

use Illuminate\Support\Facades\Route;
use Src\Campaign\Application\CampaignPermissions;
use Src\Campaign\Interface\Http\Controllers\CampaignController;

// Every campaign of the platform and of the companies, for admins with `campaigns.manage`.
Route::prefix('admin')->middleware(['auth:admin', 'can:'.CampaignPermissions::CAMPAIGNS_MANAGE])->group(function (): void {
    Route::get('campaigns', [CampaignController::class, 'index']);
    Route::post('campaigns', [CampaignController::class, 'store']);
    Route::get('campaigns/{id}', [CampaignController::class, 'show'])->whereNumber('id');
    Route::put('campaigns/{id}', [CampaignController::class, 'update'])->whereNumber('id');
    Route::delete('campaigns/{id}', [CampaignController::class, 'destroy'])->whereNumber('id');
    Route::get('campaigns/{id}/results', [CampaignController::class, 'results'])->whereNumber('id');
    Route::get('campaigns/{id}/qr', [CampaignController::class, 'qr'])->whereNumber('id');
});
