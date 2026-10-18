<?php

use Illuminate\Support\Facades\Route;
use Src\Content\Application\ContentPermissions;
use Src\Content\Interface\Http\Controllers\AdminAnnouncementController;
use Src\Content\Interface\Http\Controllers\AdminFaqCategoryController;
use Src\Content\Interface\Http\Controllers\AdminFaqController;
use Src\Content\Interface\Http\Controllers\AdminLegalDocumentController;

Route::prefix('admin')->middleware('auth:admin')->group(function (): void {
    Route::middleware('can:'.ContentPermissions::LEGAL_DOCUMENTS_MANAGE)->group(function (): void {
        Route::get('legal-documents', [AdminLegalDocumentController::class, 'index']);
        Route::post('legal-documents', [AdminLegalDocumentController::class, 'store']);
        Route::get('legal-documents/{id}', [AdminLegalDocumentController::class, 'show'])->whereNumber('id');
        Route::put('legal-documents/{id}', [AdminLegalDocumentController::class, 'update'])->whereNumber('id');
        Route::delete('legal-documents/{id}', [AdminLegalDocumentController::class, 'destroy'])->whereNumber('id');
        Route::post('legal-documents/{id}/publish', [AdminLegalDocumentController::class, 'publish'])->whereNumber('id');
    });

    Route::middleware('can:'.ContentPermissions::FAQS_MANAGE)->group(function (): void {
        Route::get('faq-categories', [AdminFaqCategoryController::class, 'index']);
        Route::post('faq-categories', [AdminFaqCategoryController::class, 'store']);
        Route::put('faq-categories/{id}', [AdminFaqCategoryController::class, 'update'])->whereNumber('id');
        Route::delete('faq-categories/{id}', [AdminFaqCategoryController::class, 'destroy'])->whereNumber('id');

        Route::get('faqs', [AdminFaqController::class, 'index']);
        Route::post('faqs', [AdminFaqController::class, 'store']);
        Route::put('faqs/{id}', [AdminFaqController::class, 'update'])->whereNumber('id');
        Route::delete('faqs/{id}', [AdminFaqController::class, 'destroy'])->whereNumber('id');
    });

    Route::middleware('can:'.ContentPermissions::ANNOUNCEMENTS_MANAGE)->group(function (): void {
        Route::get('announcements', [AdminAnnouncementController::class, 'index']);
        Route::post('announcements', [AdminAnnouncementController::class, 'store']);
        Route::get('announcements/{id}', [AdminAnnouncementController::class, 'show'])->whereNumber('id');
        Route::put('announcements/{id}', [AdminAnnouncementController::class, 'update'])->whereNumber('id');
        Route::delete('announcements/{id}', [AdminAnnouncementController::class, 'destroy'])->whereNumber('id');
    });
});
