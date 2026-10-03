<?php

namespace Src\Company\Infrastructure;

use Src\Company\Domain\ApplicationGroupRepository;
use Src\Company\Domain\BookmarkNotifier;
use Src\Company\Domain\BookmarkRepository;
use Src\Company\Domain\CompanyDirectory;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Infrastructure\Persistence\EloquentApplicationGroupRepository;
use Src\Company\Infrastructure\Persistence\EloquentBookmarkRepository;
use Src\Company\Infrastructure\Persistence\EloquentCompanyDirectory;
use Src\Company\Infrastructure\Persistence\EloquentCompanyRepository;
use Src\Shared\Infrastructure\ContextServiceProvider;

class CompanyServiceProvider extends ContextServiceProvider
{
    public array $bindings = [
        CompanyRepository::class => EloquentCompanyRepository::class,
        BookmarkRepository::class => EloquentBookmarkRepository::class,
        ApplicationGroupRepository::class => EloquentApplicationGroupRepository::class,
        CompanyDirectory::class => EloquentCompanyDirectory::class,
        BookmarkNotifier::class => BookmarkNotificationsAdapter::class,
    ];

    public function register(): void
    {
        $this->app->tag(CompanyProfileProjection::class, 'user-profile-projections');
    }
}
