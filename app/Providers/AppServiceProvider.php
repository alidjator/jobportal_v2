<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Images\ImageProcessor;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\RequestContext;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Infrastructure\Documentation\ApiDocumentation;
use Src\Shared\Infrastructure\Documentation\ErrorCatalogCommand;
use Src\Shared\Infrastructure\Events\LaravelEventDispatcher;
use Src\Shared\Infrastructure\Images\GdImageProcessor;
use Src\Shared\Infrastructure\Persistence\LaravelTransactionManager;
use Src\Shared\Infrastructure\Storage\LaravelFileStorage;
use Src\Shared\Infrastructure\Testing\MigrateTestDatabaseCommand;
use Src\Shared\Infrastructure\Time\SystemClock;

class AppServiceProvider extends ServiceProvider
{
    /** @var list<class-string> */
    public array $singletons = [
        PermissionCatalog::class,
        SettingsCatalog::class,
        EmailTemplateCatalog::class,
        NotificationPreferenceCatalog::class,
    ];

    /** @var array<class-string, class-string> */
    public array $bindings = [
        TransactionManager::class => LaravelTransactionManager::class,
        EventDispatcher::class => LaravelEventDispatcher::class,
        Clock::class => SystemClock::class,
        FileStorage::class => LaravelFileStorage::class,
        ImageProcessor::class => GdImageProcessor::class,
    ];

    public function register(): void
    {
        $this->app->scoped(RequestContext::class, fn () => new RequestContext);

        $this->commands([MigrateTestDatabaseCommand::class, ErrorCatalogCommand::class]);
    }

    public function boot(): void
    {
        ApiDocumentation::configure($this->app->isProduction());
    }
}
