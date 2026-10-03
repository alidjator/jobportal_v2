<?php

namespace Src\Shared\Infrastructure;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Base provider tiap bounded context. Subclass cukup menaruh `Interface/Http/routes.php` (boleh juga `routes.<nama>.php`)
 * (dimuat dengan prefix `api` + middleware group `api`) dan mendeklarasikan
 * binding interface → implementasi lewat `public array $bindings` (fitur bawaan Laravel).
 */
abstract class ContextServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $http = dirname((new ReflectionClass($this))->getFileName()).'/../Interface/Http';

        foreach ([$http.'/routes.php', ...glob($http.'/routes.*.php') ?: []] as $routes) {
            if (is_file($routes)) {
                Route::middleware('api')->prefix('api')->group($routes);
            }
        }
    }
}
