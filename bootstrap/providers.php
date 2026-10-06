<?php

use App\Providers\AppServiceProvider;
use Src\Company\Infrastructure\CompanyServiceProvider;
use Src\Content\Infrastructure\ContentServiceProvider;
use Src\Identity\Infrastructure\IdentityServiceProvider;
use Src\Job\Infrastructure\JobServiceProvider;
use Src\JobSeeker\Infrastructure\JobSeekerServiceProvider;
use Src\Notification\Infrastructure\NotificationServiceProvider;
use Src\Platform\Infrastructure\PlatformServiceProvider;
use Src\Recruitment\Infrastructure\RecruitmentServiceProvider;
use Src\ReferenceData\Infrastructure\ReferenceDataServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    NotificationServiceProvider::class,
    PlatformServiceProvider::class,
    ContentServiceProvider::class,
    ReferenceDataServiceProvider::class,
    CompanyServiceProvider::class,
    JobServiceProvider::class,
    RecruitmentServiceProvider::class,
    JobSeekerServiceProvider::class,
];
