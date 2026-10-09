<?php

namespace Src\Platform\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Src\Campaign\Domain\Events\CampaignChanged;
use Src\Company\Domain\Events\CompanyCreated;
use Src\Company\Domain\Events\CompanyDocumentApproved;
use Src\Company\Domain\Events\CompanyDocumentDeleted;
use Src\Company\Domain\Events\CompanyDocumentRejected;
use Src\Company\Domain\Events\CompanyDocumentSubmitted;
use Src\Company\Domain\Events\CompanyDocumentViewed;
use Src\Company\Domain\Events\CompanyInvitationRevoked;
use Src\Company\Domain\Events\CompanyInvitationSent;
use Src\Company\Domain\Events\CompanyMemberJoined;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Company\Domain\Events\CompanyMemberRoleChanged;
use Src\Company\Domain\Events\CompanyRestored;
use Src\Company\Domain\Events\CompanySuspended;
use Src\Company\Domain\Events\DocumentTypeCreated;
use Src\Company\Domain\Events\DocumentTypeVerificationRulesChanged;
use Src\Identity\Domain\Events\AccountDeletionCancelled;
use Src\Identity\Domain\Events\AccountDeletionRequested;
use Src\Identity\Domain\Events\PasswordReset;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Job\Domain\Events\JobAssigneesChanged;
use Src\Job\Domain\Events\JobDecided;
use Src\Job\Domain\Events\JobStatusChanged;
use Src\Platform\Application\Audit\AuditLogReader;
use Src\Platform\Application\Audit\AuditLogRepository;
use Src\Platform\Application\Email\EmailBodyRenderer;
use Src\Platform\Application\Email\EmailTemplateOverrideRepository;
use Src\Platform\Application\Email\PlatformEmailTemplates;
use Src\Platform\Application\PlatformPermissions;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Platform\Application\Settings\StoredSettings;
use Src\Platform\Infrastructure\Dashboard\OperationsCard;
use Src\Platform\Infrastructure\Email\CommonMarkEmailBodyRenderer;
use Src\Platform\Infrastructure\Listeners\RecordCampaignAuditEntries;
use Src\Platform\Infrastructure\Listeners\RecordCompanyAuditEntries;
use Src\Platform\Infrastructure\Listeners\RecordIdentityAuditEntries;
use Src\Platform\Infrastructure\Listeners\RecordJobAuditEntries;
use Src\Platform\Infrastructure\Listeners\RecordRecruitmentAuditEntries;
use Src\Platform\Infrastructure\Persistence\DatabaseAuditLogReader;
use Src\Platform\Infrastructure\Persistence\DatabaseAuditLogRepository;
use Src\Platform\Infrastructure\Persistence\DatabaseEmailTemplateOverrideRepository;
use Src\Platform\Infrastructure\Persistence\DatabaseSettingsStore;
use Src\Platform\Interface\Console\PurgeAuditLogsCommand;
use Src\Recruitment\Domain\Events\ApplicantsExported;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Email\EmailTemplates;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class PlatformServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        AuditLogRepository::class => DatabaseAuditLogRepository::class,
        AuditLogReader::class => DatabaseAuditLogReader::class,
        Settings::class => StoredSettings::class,
        EmailTemplateOverrideRepository::class => DatabaseEmailTemplateOverrideRepository::class,
        EmailBodyRenderer::class => CommonMarkEmailBodyRenderer::class,
        EmailTemplates::class => PlatformEmailTemplates::class,
    ];

    /** One store per request, so the in-memory copy of the values is shared and dropped on every write. @var array<class-string, class-string> */
    public array $singletons = [
        SettingsStore::class => DatabaseSettingsStore::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(DashboardCards::class, function (DashboardCards $cards): void {
            $cards->add(
                $this->app->make(OperationsCard::class),
            );
        });

        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...PlatformPermissions::definitions());
        });

        $this->commands([PurgeAuditLogsCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PurgeAuditLogsCommand::class)->dailyAt('03:00')->withoutOverlapping()->onOneServer();
        });
    }

    public function boot(): void
    {
        foreach ([
            SignedIn::class => 'onSignedIn',
            SignInFailed::class => 'onSignInFailed',
            PasswordReset::class => 'onPasswordReset',
            AccountDeletionRequested::class => 'onAccountDeletionRequested',
            AccountDeletionCancelled::class => 'onAccountDeletionCancelled',
            UserAnonymized::class => 'onUserAnonymized',
        ] as $event => $method) {
            Event::listen($event, [RecordIdentityAuditEntries::class, $method]);
        }

        Event::listen(CompanyCreated::class, [RecordCompanyAuditEntries::class, 'onCompanyCreated']);
        Event::listen(CompanySuspended::class, [RecordCompanyAuditEntries::class, 'onCompanySuspended']);
        Event::listen(CompanyRestored::class, [RecordCompanyAuditEntries::class, 'onCompanyRestored']);
        Event::listen(CompanyInvitationSent::class, [RecordCompanyAuditEntries::class, 'onCompanyInvitationSent']);
        Event::listen(CompanyInvitationRevoked::class, [RecordCompanyAuditEntries::class, 'onCompanyInvitationRevoked']);
        Event::listen(CompanyMemberJoined::class, [RecordCompanyAuditEntries::class, 'onCompanyMemberJoined']);
        Event::listen(CompanyMemberRemoved::class, [RecordCompanyAuditEntries::class, 'onCompanyMemberRemoved']);
        Event::listen(CompanyMemberRoleChanged::class, [RecordCompanyAuditEntries::class, 'onCompanyMemberRoleChanged']);
        Event::listen(CompanyDocumentSubmitted::class, [RecordCompanyAuditEntries::class, 'onCompanyDocumentSubmitted']);
        Event::listen(CompanyDocumentDeleted::class, [RecordCompanyAuditEntries::class, 'onCompanyDocumentDeleted']);
        Event::listen(JobAssigneesChanged::class, [RecordJobAuditEntries::class, 'onJobAssigneesChanged']);
        Event::listen(JobStatusChanged::class, [RecordJobAuditEntries::class, 'onJobStatusChanged']);
        Event::listen(JobDecided::class, [RecordJobAuditEntries::class, 'onJobDecided']);
        Event::listen(CampaignChanged::class, [RecordCampaignAuditEntries::class, 'onCampaignChanged']);
        Event::listen(ApplicantsExported::class, [RecordRecruitmentAuditEntries::class, 'onApplicantsExported']);
        Event::listen(CompanyDocumentApproved::class, [RecordCompanyAuditEntries::class, 'onCompanyDocumentApproved']);
        Event::listen(CompanyDocumentRejected::class, [RecordCompanyAuditEntries::class, 'onCompanyDocumentRejected']);
        Event::listen(CompanyDocumentViewed::class, [RecordCompanyAuditEntries::class, 'onCompanyDocumentViewed']);
        Event::listen(DocumentTypeCreated::class, [RecordCompanyAuditEntries::class, 'onDocumentTypeCreated']);
        Event::listen(DocumentTypeVerificationRulesChanged::class, [RecordCompanyAuditEntries::class, 'onDocumentTypeVerificationRulesChanged']);

        parent::boot();
    }
}
