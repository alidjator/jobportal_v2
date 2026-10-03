<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Shared\Application\RequestContext;
use Src\Shared\Infrastructure\Testing\MigrateTestDatabaseCommand;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(RequestContext::class, fn () => new RequestContext);

        $this->commands([MigrateTestDatabaseCommand::class]);
    }

    public function boot(): void
    {
        //
    }
}
