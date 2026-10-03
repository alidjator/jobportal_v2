<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Shared\Application\Contracts\FileStorage;
use Src\Shared\Application\RequestContext;
use Src\Shared\Infrastructure\Storage\PublicFileStorage;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(RequestContext::class, fn () => new RequestContext);
        $this->app->bind(FileStorage::class, PublicFileStorage::class);
    }

    public function boot(): void
    {
        //
    }
}
