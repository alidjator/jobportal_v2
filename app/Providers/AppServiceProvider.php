<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\RequestContext;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Infrastructure\Events\LaravelEventDispatcher;
use Src\Shared\Infrastructure\Persistence\LaravelTransactionManager;
use Src\Shared\Infrastructure\Storage\LaravelFileStorage;
use Src\Shared\Infrastructure\Testing\MigrateTestDatabaseCommand;
use Src\Shared\Infrastructure\Time\SystemClock;

class AppServiceProvider extends ServiceProvider
{
    /** @var list<class-string> */
    public array $singletons = [
        PermissionCatalog::class,
    ];

    /** @var array<class-string, class-string> */
    public array $bindings = [
        TransactionManager::class => LaravelTransactionManager::class,
        EventDispatcher::class => LaravelEventDispatcher::class,
        Clock::class => SystemClock::class,
        FileStorage::class => LaravelFileStorage::class,
    ];

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
