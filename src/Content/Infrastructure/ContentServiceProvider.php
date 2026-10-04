<?php

namespace Src\Content\Infrastructure;

use Src\Content\Application\ContentPermissions;
use Src\Content\Domain\FaqCategoryRepository;
use Src\Content\Domain\FaqRepository;
use Src\Content\Domain\LegalDocumentRepository;
use Src\Content\Infrastructure\Persistence\Eloquent\DatabaseActiveLegalVersions;
use Src\Content\Infrastructure\Persistence\Eloquent\EloquentFaqCategoryRepository;
use Src\Content\Infrastructure\Persistence\Eloquent\EloquentFaqRepository;
use Src\Content\Infrastructure\Persistence\Eloquent\EloquentLegalDocumentRepository;
use Src\Shared\Application\Legal\ActiveLegalVersions;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class ContentServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        LegalDocumentRepository::class => EloquentLegalDocumentRepository::class,
        FaqCategoryRepository::class => EloquentFaqCategoryRepository::class,
        FaqRepository::class => EloquentFaqRepository::class,
        ActiveLegalVersions::class => DatabaseActiveLegalVersions::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...ContentPermissions::definitions());
        });
    }
}
