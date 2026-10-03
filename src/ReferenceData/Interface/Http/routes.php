<?php

use Illuminate\Support\Facades\Route;
use Src\ReferenceData\Interface\Http\Controllers\ReferenceDataController;

Route::controller(ReferenceDataController::class)->group(function () {
    Route::get('/languages', 'languages');
    Route::get('/change-language/{code}', 'changeLanguage');
    Route::get('/language/translations', 'translations');
    Route::get('/localizations/{locale}/strings', 'localizationStrings');
    Route::get('/countries', 'countries');
    Route::get('/change-country/{countryId}', 'changeCountry')->whereNumber('countryId');
    Route::get('/currencies', 'currencies');
    Route::get('/change-currency/{code}', 'changeCurrency');
    Route::get('/categories', 'categories');
    Route::get('/job-roles', 'jobRoles');
    Route::get('/experiences', 'experiences');
    Route::get('/educations', 'educations');
    Route::get('/job-types', 'jobTypes');
    Route::get('/popular-tags', 'popularTags');
    Route::get('/professions', 'professions');
    Route::get('/current-session', 'currentSession');
});
