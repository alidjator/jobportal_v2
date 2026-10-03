<?php

use App\Providers\AppServiceProvider;
use Src\Billing\Infrastructure\BillingServiceProvider;
use Src\CandidateProfile\Infrastructure\CandidateProfileServiceProvider;
use Src\Company\Infrastructure\CompanyServiceProvider;
use Src\Content\Infrastructure\ContentServiceProvider;
use Src\Identity\Infrastructure\IdentityServiceProvider;
use Src\Notification\Infrastructure\NotificationServiceProvider;
use Src\Recruitment\Infrastructure\RecruitmentServiceProvider;
use Src\ReferenceData\Infrastructure\ReferenceDataServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    CandidateProfileServiceProvider::class,
    CompanyServiceProvider::class,
    RecruitmentServiceProvider::class,
    BillingServiceProvider::class,
    ContentServiceProvider::class,
    NotificationServiceProvider::class,
    ReferenceDataServiceProvider::class,
];
