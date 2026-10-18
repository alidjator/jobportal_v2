<?php

use Illuminate\Support\Facades\Route;
use Src\Content\Interface\Http\Controllers\AnnouncementController;
use Src\Content\Interface\Http\Controllers\PublicFaqController;
use Src\Content\Interface\Http\Controllers\PublicLegalDocumentController;

Route::get('legal-documents/{type}', [PublicLegalDocumentController::class, 'active']);
Route::get('legal-documents/{type}/versions/{version}', [PublicLegalDocumentController::class, 'version'])->whereNumber('version');
Route::get('faq', [PublicFaqController::class, 'index']);
Route::get('announcements', [AnnouncementController::class, 'index']);
