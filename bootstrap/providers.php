<?php

use App\Providers\AppServiceProvider;
use Src\Company\Infrastructure\CompanyServiceProvider;
use Src\Content\Infrastructure\ContentServiceProvider;
use Src\Identity\Infrastructure\IdentityServiceProvider;
use Src\Notification\Infrastructure\NotificationServiceProvider;
use Src\Platform\Infrastructure\PlatformServiceProvider;
use Src\ReferenceData\Infrastructure\ReferenceDataServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    NotificationServiceProvider::class,
    PlatformServiceProvider::class,
    ContentServiceProvider::class,
    ReferenceDataServiceProvider::class,
    CompanyServiceProvider::class,
];
