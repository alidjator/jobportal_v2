<?php

use Illuminate\Support\Facades\Route;
use Src\JobSeeker\Application\JobSeekerPermissions;
use Src\JobSeeker\Interface\Http\Controllers\AdminCvTemplateController;

Route::prefix('admin')->middleware(['auth:admin', 'can:'.JobSeekerPermissions::CV_TEMPLATES_MANAGE])->group(function (): void {
    Route::get('cv-templates', [AdminCvTemplateController::class, 'index']);
    Route::patch('cv-templates/{key}', [AdminCvTemplateController::class, 'update'])->where('key', '[a-z0-9_-]+');
});
