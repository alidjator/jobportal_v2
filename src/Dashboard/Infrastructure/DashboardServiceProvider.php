<?php

namespace Src\Dashboard\Infrastructure;

use Src\Dashboard\Application\DashboardCache;
use Src\Dashboard\Application\DashboardPermissions;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class DashboardServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        DashboardCache::class => LaravelDashboardCache::class,
    ];

    /** @var array<class-string, class-string> One list of cards for the whole application; every context adds its own. */
    public array $singletons = [
        DashboardCards::class => DashboardCards::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...DashboardPermissions::definitions());
        });
    }
}
