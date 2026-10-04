<?php

use Illuminate\Support\Facades\Route;
use Src\ReferenceData\Application\Locations\LocationCatalog;
use Src\ReferenceData\Application\Masters\MasterCatalog;
use Src\ReferenceData\Interface\Http\Controllers\PublicLocationController;
use Src\ReferenceData\Interface\Http\Controllers\PublicMasterController;

// Public reference data changes rarely: clients and caches may keep it for an hour and revalidate with the ETag.
Route::middleware('cache.headers:public;max_age=3600;etag')->group(function (): void {
    foreach (MasterCatalog::all() as $definition) {
        Route::get($definition->key, [PublicMasterController::class, 'index'])->defaults('master', $definition->key);
    }

    foreach (LocationCatalog::all() as $level) {
        Route::get($level->key, [PublicLocationController::class, 'index'])->defaults('level', $level->key);
    }
});
