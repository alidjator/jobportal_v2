<?php

use Illuminate\Support\Facades\Route;
use Src\Billing\Interface\Http\Controllers\BillingController;

Route::get('/pricing', [BillingController::class, 'pricing']);

Route::middleware(['auth:sanctum', 'api_company'])->prefix('company')->controller(BillingController::class)->group(function () {
    Route::get('/plan', 'plan');
    Route::get('/plan-limit', 'planLimit');
    Route::get('/download-invoice/{id}', 'downloadInvoice')->whereNumber('id');
});
