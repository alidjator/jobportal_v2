<?php

namespace Src\Shared\Infrastructure;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Base provider of every bounded context. A subclass only has to put its routes in `Interface/Http/routes.php`
 * (or `routes.<name>.php`), which are loaded with the prefix `api/v1` and the `api` middleware group, and declare
 * interface-to-implementation bindings through the `public array $bindings` property (a built-in Laravel feature).
 */
abstract class ContextServiceProvider extends ServiceProvider
{
    public const string API_PREFIX = 'api/v1';

    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $http = dirname((new ReflectionClass($this))->getFileName()).'/../Interface/Http';

        foreach ([$http.'/routes.php', ...glob($http.'/routes.*.php') ?: []] as $routes) {
            if (is_file($routes)) {
                Route::middleware('api')->prefix(self::API_PREFIX)->group($routes);
            }
        }
    }
}
