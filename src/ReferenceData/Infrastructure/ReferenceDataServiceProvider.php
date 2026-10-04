<?php

namespace Src\ReferenceData\Infrastructure;

use Src\ReferenceData\Application\Locations\LocationRepository;
use Src\ReferenceData\Application\Masters\MasterRepository;
use Src\ReferenceData\Application\Masters\SlugGenerator;
use Src\ReferenceData\Application\ReferenceDataPermissions;
use Src\ReferenceData\Infrastructure\Persistence\DatabaseLocationRepository;
use Src\ReferenceData\Infrastructure\Persistence\DatabaseMasterRepository;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class ReferenceDataServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        MasterRepository::class => DatabaseMasterRepository::class,
        LocationRepository::class => DatabaseLocationRepository::class,
        SlugGenerator::class => LaravelSlugGenerator::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...ReferenceDataPermissions::definitions());
        });
    }
}
