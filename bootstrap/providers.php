<?php

use App\Providers\AppServiceProvider;
use Src\Campaign\Infrastructure\CampaignServiceProvider;
use Src\Company\Infrastructure\CompanyServiceProvider;
use Src\Content\Infrastructure\ContentServiceProvider;
use Src\Dashboard\Infrastructure\DashboardServiceProvider;
use Src\Identity\Infrastructure\IdentityServiceProvider;
use Src\Job\Infrastructure\JobServiceProvider;
use Src\JobAlert\Infrastructure\JobAlertServiceProvider;
use Src\JobInvitation\Infrastructure\JobInvitationServiceProvider;
use Src\JobSeeker\Infrastructure\JobSeekerServiceProvider;
use Src\LegacyImport\Infrastructure\LegacyImportServiceProvider;
use Src\Matching\Infrastructure\MatchingServiceProvider;
use Src\Messaging\Infrastructure\MessagingServiceProvider;
use Src\Notification\Infrastructure\NotificationServiceProvider;
use Src\Platform\Infrastructure\PlatformServiceProvider;
use Src\Recruitment\Infrastructure\RecruitmentServiceProvider;
use Src\ReferenceData\Infrastructure\ReferenceDataServiceProvider;
use Src\Training\Infrastructure\TrainingServiceProvider;

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
    MessagingServiceProvider::class,
    JobAlertServiceProvider::class,
    CampaignServiceProvider::class,
    TrainingServiceProvider::class,
    JobInvitationServiceProvider::class,
    MatchingServiceProvider::class,
    JobSeekerServiceProvider::class,
    LegacyImportServiceProvider::class,
    DashboardServiceProvider::class,
];
