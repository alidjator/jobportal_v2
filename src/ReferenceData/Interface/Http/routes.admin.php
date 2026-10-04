<?php

use Illuminate\Support\Facades\Route;
use Src\ReferenceData\Application\Masters\MasterCatalog;
use Src\ReferenceData\Application\ReferenceDataPermissions;
use Src\ReferenceData\Interface\Http\Controllers\AdminMasterController;

Route::prefix('admin')->middleware(['auth:admin', 'can:'.ReferenceDataPermissions::MANAGE])->group(function (): void {
    foreach (MasterCatalog::all() as $definition) {
        if (! $definition->adminManaged) {
            continue;
        }

        Route::get($definition->key, [AdminMasterController::class, 'index'])->defaults('master', $definition->key);
        Route::post($definition->key, [AdminMasterController::class, 'store'])->defaults('master', $definition->key);
        Route::put("{$definition->key}/{id}", [AdminMasterController::class, 'update'])->defaults('master', $definition->key)->whereNumber('id');
    }
});
