<?php

namespace Src\ReferenceData\Infrastructure;

use Src\ReferenceData\Application\Locations\LocationRepository;
use Src\ReferenceData\Application\Masters\MasterReferenceValues;
use Src\ReferenceData\Application\Masters\MasterRepository;
use Src\ReferenceData\Application\Masters\SlugGenerator;
use Src\ReferenceData\Application\ReferenceDataPermissions;
use Src\ReferenceData\Infrastructure\Persistence\DatabaseLocationReferences;
use Src\ReferenceData\Infrastructure\Persistence\DatabaseLocationRepository;
use Src\ReferenceData\Infrastructure\Persistence\DatabaseMasterRepository;
use Src\ReferenceData\Infrastructure\Persistence\DatabaseTagRegistry;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Application\References\TagRegistry;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class ReferenceDataServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        MasterRepository::class => DatabaseMasterRepository::class,
        LocationRepository::class => DatabaseLocationRepository::class,
        SlugGenerator::class => LaravelSlugGenerator::class,
        ReferenceValues::class => MasterReferenceValues::class,
        LocationReferences::class => DatabaseLocationReferences::class,
        TagRegistry::class => DatabaseTagRegistry::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...ReferenceDataPermissions::definitions());
        });
    }
}
