<?php

use App\Providers\AppServiceProvider;
use Src\Approval\Infrastructure\ApprovalServiceProvider;
use Src\Assessment\Infrastructure\AssessmentServiceProvider;
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
use Src\Newsletter\Infrastructure\NewsletterServiceProvider;
use Src\Notification\Infrastructure\NotificationServiceProvider;
use Src\Organization\Infrastructure\OrganizationServiceProvider;
use Src\Platform\Infrastructure\PlatformServiceProvider;
use Src\Recruitment\Infrastructure\RecruitmentServiceProvider;
use Src\ReferenceData\Infrastructure\ReferenceDataServiceProvider;
use Src\Review\Infrastructure\ReviewServiceProvider;
use Src\TalentPool\Infrastructure\TalentPoolServiceProvider;
use Src\Training\Infrastructure\TrainingServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    NotificationServiceProvider::class,
    PlatformServiceProvider::class,
    ContentServiceProvider::class,
    ReferenceDataServiceProvider::class,
    CompanyServiceProvider::class,
    OrganizationServiceProvider::class,
    ApprovalServiceProvider::class,
    JobServiceProvider::class,
    RecruitmentServiceProvider::class,
    MessagingServiceProvider::class,
    JobAlertServiceProvider::class,
    CampaignServiceProvider::class,
    TrainingServiceProvider::class,
    NewsletterServiceProvider::class,
    JobInvitationServiceProvider::class,
    MatchingServiceProvider::class,
    JobSeekerServiceProvider::class,
    LegacyImportServiceProvider::class,
    DashboardServiceProvider::class,
    ReviewServiceProvider::class,
    TalentPoolServiceProvider::class,
    AssessmentServiceProvider::class,
];
