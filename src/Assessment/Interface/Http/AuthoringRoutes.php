<?php

namespace Src\Assessment\Interface\Http;

use Illuminate\Support\Facades\Route;
use Src\Assessment\Interface\Http\Controllers\QuestionController;
use Src\Assessment\Interface\Http\Controllers\SectionController;
use Src\Assessment\Interface\Http\Controllers\TestController;

/** The authoring routes, registered once for each kind of author: a company admin, a psychologist and an admin of the library. */
final class AuthoringRoutes
{
    public static function register(): void
    {
        Route::get('tests', [TestController::class, 'index']);
        Route::post('tests', [TestController::class, 'store']);
        Route::get('tests/{id}', [TestController::class, 'show'])->whereNumber('id');
        Route::put('tests/{id}', [TestController::class, 'update'])->whereNumber('id');
        Route::delete('tests/{id}', [TestController::class, 'destroy'])->whereNumber('id');
        Route::post('tests/{id}/publish', [TestController::class, 'publish'])->whereNumber('id');
        Route::post('tests/{id}/archive', [TestController::class, 'archive'])->whereNumber('id');
        Route::post('tests/{id}/restore', [TestController::class, 'restore'])->whereNumber('id');
        Route::post('tests/{id}/clone', [TestController::class, 'duplicate'])->whereNumber('id');

        Route::post('tests/{id}/sections', [SectionController::class, 'store'])->whereNumber('id');
        Route::put('tests/{id}/sections/order', [SectionController::class, 'reorder'])->whereNumber('id');
        Route::put('sections/{sectionId}', [SectionController::class, 'update'])->whereNumber('sectionId');
        Route::delete('sections/{sectionId}', [SectionController::class, 'destroy'])->whereNumber('sectionId');

        Route::get('sections/{sectionId}/questions', [QuestionController::class, 'index'])->whereNumber('sectionId');
        Route::post('sections/{sectionId}/questions', [QuestionController::class, 'store'])->whereNumber('sectionId');
        Route::put('sections/{sectionId}/questions/order', [QuestionController::class, 'reorder'])->whereNumber('sectionId');
        Route::put('questions/{questionId}', [QuestionController::class, 'update'])->whereNumber('questionId');
        Route::delete('questions/{questionId}', [QuestionController::class, 'destroy'])->whereNumber('questionId');
    }
}
