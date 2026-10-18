<?php

namespace Src\Company\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Src\Company\Application\Admin\AdminCompanyReader;
use Src\Company\Application\CompanyEmailTemplates;
use Src\Company\Application\CompanyPermissions;
use Src\Company\Application\CompanySlugGenerator;
use Src\Company\Application\Images\CompanySettings;
use Src\Company\Application\Invitations\InvitationLinkBuilder;
use Src\Company\Application\Invitations\InvitationSender;
use Src\Company\Application\Invitations\InvitationThrottle;
use Src\Company\Application\Messages\CompanyCandidateMessageRules;
use Src\Company\Application\Messages\RepositoryCompanyMessageTexts;
use Src\Company\Application\Team\CompanyTwoFactorPolicy;
use Src\Company\Application\Team\LastCompanyAdminDeletionGuard;
use Src\Company\Application\Team\MemberNoticeSender;
use Src\Company\Application\Verification\CompanyVerificationBadge;
use Src\Company\Domain\CompanyDocumentRepository;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\DocumentTypeRepository;
use Src\Company\Domain\Events\CompanyCreated;
use Src\Company\Domain\Events\CompanyDocumentApproved;
use Src\Company\Domain\Events\CompanyDocumentDeleted;
use Src\Company\Domain\Events\CompanyDocumentRejected;
use Src\Company\Domain\Events\CompanyDocumentSubmitted;
use Src\Company\Domain\Events\CompanyDocumentViewed;
use Src\Company\Domain\Events\CompanyEmailDomainsChanged;
use Src\Company\Domain\Events\CompanyInvitationResent;
use Src\Company\Domain\Events\CompanyInvitationRevoked;
use Src\Company\Domain\Events\CompanyInvitationSent;
use Src\Company\Domain\Events\CompanyInvitationUpdated;
use Src\Company\Domain\Events\CompanyMemberJoined;
use Src\Company\Domain\Events\CompanyMemberPermissionsChanged;
use Src\Company\Domain\Events\CompanyMemberReactivated;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Company\Domain\Events\CompanyMemberRenamed;
use Src\Company\Domain\Events\CompanyMemberRoleChanged;
use Src\Company\Domain\Events\CompanyMemberSuspended;
use Src\Company\Domain\Events\CompanyMemberTwoFactorReset;
use Src\Company\Domain\Events\CompanyMessageDefaultsChanged;
use Src\Company\Domain\Events\CompanyNameChangeCancelled;
use Src\Company\Domain\Events\CompanyNameChangeDecided;
use Src\Company\Domain\Events\CompanyNameChangeDocumentViewed;
use Src\Company\Domain\Events\CompanyNameChangeRequested;
use Src\Company\Domain\Events\CompanyOwnerTransferred;
use Src\Company\Domain\Events\CompanyRestored;
use Src\Company\Domain\Events\CompanySuspended;
use Src\Company\Domain\Events\CompanyVerificationGranted;
use Src\Company\Domain\Events\CompanyVerificationWithdrawn;
use Src\Company\Domain\Events\DocumentTypeCreated;
use Src\Company\Domain\Events\DocumentTypeVerificationRulesChanged;
use Src\Company\Domain\InvitationRepository;
use Src\Company\Domain\MessageDefaultsRepository;
use Src\Company\Domain\NameChangeRequestRepository;
use Src\Company\Infrastructure\Dashboard\CompanyVerificationQueueCard;
use Src\Company\Infrastructure\Dashboard\PlatformCompaniesCard;
use Src\Company\Infrastructure\Listeners\NotifyCompanyAdminsOfNameChange;
use Src\Company\Infrastructure\Listeners\NotifyCompanyAdminsOfVerification;
use Src\Company\Infrastructure\Listeners\NotifyMemberOfPermissionChange;
use Src\Company\Infrastructure\Listeners\RecordCompanyAuditEntries;
use Src\Company\Infrastructure\Listeners\ReleaseMembershipWhenUserAnonymized;
use Src\Company\Infrastructure\Listeners\RequireTwoFactorWhenCompanySwitchesItOn;
use Src\Company\Infrastructure\Mail\MailInvitationSender;
use Src\Company\Infrastructure\Mail\MailMemberNoticeSender;
use Src\Company\Infrastructure\Persistence\DatabaseAdminCompanyReader;
use Src\Company\Infrastructure\Persistence\DatabaseCompanyDocumentRepository;
use Src\Company\Infrastructure\Persistence\DatabaseCompanyMemberRepository;
use Src\Company\Infrastructure\Persistence\DatabaseCompanyRepository;
use Src\Company\Infrastructure\Persistence\DatabaseCompanyVerificationBadge;
use Src\Company\Infrastructure\Persistence\DatabaseDocumentTypeRepository;
use Src\Company\Infrastructure\Persistence\DatabaseInvitationRepository;
use Src\Company\Infrastructure\Persistence\DatabaseMessageDefaultsRepository;
use Src\Company\Infrastructure\Persistence\DatabaseNameChangeRequestRepository;
use Src\Company\Infrastructure\Persistence\MemberRepositoryMembershipResolver;
use Src\Company\Infrastructure\Persistence\RepositoryCompanyDirectory;
use Src\Company\Infrastructure\Persistence\RepositoryCompanyLetterheads;
use Src\Company\Infrastructure\Persistence\RepositoryCompanyTeam;
use Src\Company\Infrastructure\Persistence\RepositoryMemberSignInGuard;
use Src\Company\Infrastructure\Persistence\RepositoryPublicCompanies;
use Src\Company\Infrastructure\Persistence\RepositorySuspendedMembers;
use Src\Company\Infrastructure\Persistence\RepositoryTenantRegistry;
use Src\Company\Interface\Console\PurgeEndedInvitationsCommand;
use Src\Company\Interface\Console\PurgeNameChangeDocumentsCommand;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyLetterheads;
use Src\Shared\Application\Company\CompanyMemberDeletionGuard;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Company\CompanyMessageTexts;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Company\MemberSignInGuard;
use Src\Shared\Application\Company\MemberTwoFactorPolicy;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\Company\SuspendedMembers;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Email\CompanyEmailFrame;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Settings\CompanySettingChanged;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class CompanyServiceProvider extends ContextServiceProvider
{
    public const string ACTIVITY_EXPORT_LIMITER = 'company-activity-export';

    private const int ACTIVITY_EXPORTS_PER_HOUR = 10;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        CompanyRepository::class => DatabaseCompanyRepository::class,
        CompanyMemberRepository::class => DatabaseCompanyMemberRepository::class,
        AdminCompanyReader::class => DatabaseAdminCompanyReader::class,
        CompanyDirectory::class => RepositoryCompanyDirectory::class,
        TenantRegistry::class => RepositoryTenantRegistry::class,
        CompanyTeam::class => RepositoryCompanyTeam::class,
        CompanyMemberDeletionGuard::class => LastCompanyAdminDeletionGuard::class,
        PublicCompanies::class => RepositoryPublicCompanies::class,
        CompanyLetterheads::class => RepositoryCompanyLetterheads::class,
        InvitationRepository::class => DatabaseInvitationRepository::class,
        CompanyMembershipResolver::class => MemberRepositoryMembershipResolver::class,
        MemberTwoFactorPolicy::class => CompanyTwoFactorPolicy::class,
        CompanySlugGenerator::class => LaravelCompanySlugGenerator::class,
        InvitationLinkBuilder::class => ConfiguredInvitationLinkBuilder::class,
        InvitationThrottle::class => RateLimiterInvitationThrottle::class,
        InvitationSender::class => MailInvitationSender::class,
        MemberNoticeSender::class => MailMemberNoticeSender::class,
        MemberSignInGuard::class => RepositoryMemberSignInGuard::class,
        SuspendedMembers::class => RepositorySuspendedMembers::class,
        DocumentTypeRepository::class => DatabaseDocumentTypeRepository::class,
        CompanyDocumentRepository::class => DatabaseCompanyDocumentRepository::class,
        NameChangeRequestRepository::class => DatabaseNameChangeRequestRepository::class,
        CompanyVerificationBadge::class => DatabaseCompanyVerificationBadge::class,
        MessageDefaultsRepository::class => DatabaseMessageDefaultsRepository::class,
        CandidateMessageRules::class => CompanyCandidateMessageRules::class,
        CompanyMessageTexts::class => RepositoryCompanyMessageTexts::class,
    ];

    public function register(): void
    {
        $this->commands([PurgeEndedInvitationsCommand::class, PurgeNameChangeDocumentsCommand::class]);

        $this->app->bind(CompanyEmailFrame::class, fn ($app): CompanyEmailFrame => new CompanyEmailFrame($app->make(PublicCompanies::class), (string) $app->make('config')->get('app.name')));

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PurgeEndedInvitationsCommand::class)->dailyAt('03:15')->withoutOverlapping()->onOneServer();
            $schedule->command(PurgeNameChangeDocumentsCommand::class)->dailyAt('03:30')->withoutOverlapping()->onOneServer();
        });

        $this->callAfterResolving(DashboardCards::class, function (DashboardCards $cards): void {
            $cards->add(
                $this->app->make(PlatformCompaniesCard::class),
                $this->app->make(CompanyVerificationQueueCard::class),
            );
        });

        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...CompanyPermissions::definitions());
        });

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...CompanyEmailTemplates::definitions());
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...CompanySettings::definitions());
        });
    }

    public function boot(): void
    {
        RateLimiter::for(self::ACTIVITY_EXPORT_LIMITER, fn (Request $request): Limit => Limit::perHour(self::ACTIVITY_EXPORTS_PER_HOUR)->by('company-activity-export|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        Event::listen(CompanyDocumentApproved::class, [NotifyCompanyAdminsOfVerification::class, 'onDocumentApproved']);
        Event::listen(CompanyDocumentRejected::class, [NotifyCompanyAdminsOfVerification::class, 'onDocumentRejected']);
        Event::listen(CompanyVerificationGranted::class, [NotifyCompanyAdminsOfVerification::class, 'onVerificationGranted']);
        Event::listen(CompanyVerificationWithdrawn::class, [NotifyCompanyAdminsOfVerification::class, 'onVerificationWithdrawn']);

        Event::listen(UserAnonymized::class, ReleaseMembershipWhenUserAnonymized::class);

        Event::listen(CompanyCreated::class, [RecordCompanyAuditEntries::class, 'onCompanyCreated']);
        Event::listen(CompanySuspended::class, [RecordCompanyAuditEntries::class, 'onCompanySuspended']);
        Event::listen(CompanyRestored::class, [RecordCompanyAuditEntries::class, 'onCompanyRestored']);
        Event::listen(CompanyInvitationSent::class, [RecordCompanyAuditEntries::class, 'onCompanyInvitationSent']);
        Event::listen(CompanyInvitationRevoked::class, [RecordCompanyAuditEntries::class, 'onCompanyInvitationRevoked']);
        Event::listen(CompanyMemberJoined::class, [RecordCompanyAuditEntries::class, 'onCompanyMemberJoined']);
        Event::listen(CompanyMemberRemoved::class, [RecordCompanyAuditEntries::class, 'onCompanyMemberRemoved']);
        Event::listen(CompanyMemberRoleChanged::class, [RecordCompanyAuditEntries::class, 'onCompanyMemberRoleChanged']);
        Event::listen(CompanyMemberPermissionsChanged::class, [RecordCompanyAuditEntries::class, 'onCompanyMemberPermissionsChanged']);
        Event::listen(CompanyMemberRoleChanged::class, [NotifyMemberOfPermissionChange::class, 'onRoleChanged']);
        Event::listen(CompanyMemberPermissionsChanged::class, [NotifyMemberOfPermissionChange::class, 'onPermissionsChanged']);
        Event::listen(CompanyMemberRenamed::class, [RecordCompanyAuditEntries::class, 'onCompanyMemberRenamed']);
        Event::listen(CompanyMemberRenamed::class, [NotifyMemberOfPermissionChange::class, 'onRenamed']);
        Event::listen(CompanyMemberSuspended::class, [RecordCompanyAuditEntries::class, 'onCompanyMemberSuspended']);
        Event::listen(CompanyMemberTwoFactorReset::class, [RecordCompanyAuditEntries::class, 'onCompanyMemberTwoFactorReset']);
        Event::listen(CompanyMemberTwoFactorReset::class, [NotifyMemberOfPermissionChange::class, 'onTwoFactorReset']);
        Event::listen(CompanySettingChanged::class, RequireTwoFactorWhenCompanySwitchesItOn::class);
        Event::listen(CompanyMemberReactivated::class, [RecordCompanyAuditEntries::class, 'onCompanyMemberReactivated']);
        Event::listen(CompanyOwnerTransferred::class, [RecordCompanyAuditEntries::class, 'onCompanyOwnerTransferred']);
        Event::listen(CompanyInvitationUpdated::class, [RecordCompanyAuditEntries::class, 'onCompanyInvitationUpdated']);
        Event::listen(CompanyInvitationResent::class, [RecordCompanyAuditEntries::class, 'onCompanyInvitationResent']);
        Event::listen(CompanyEmailDomainsChanged::class, [RecordCompanyAuditEntries::class, 'onCompanyEmailDomainsChanged']);
        Event::listen(CompanyMessageDefaultsChanged::class, [RecordCompanyAuditEntries::class, 'onCompanyMessageDefaultsChanged']);
        Event::listen(CompanyDocumentSubmitted::class, [RecordCompanyAuditEntries::class, 'onCompanyDocumentSubmitted']);
        Event::listen(CompanyDocumentDeleted::class, [RecordCompanyAuditEntries::class, 'onCompanyDocumentDeleted']);
        Event::listen(CompanyDocumentApproved::class, [RecordCompanyAuditEntries::class, 'onCompanyDocumentApproved']);
        Event::listen(CompanyDocumentRejected::class, [RecordCompanyAuditEntries::class, 'onCompanyDocumentRejected']);
        Event::listen(CompanyDocumentViewed::class, [RecordCompanyAuditEntries::class, 'onCompanyDocumentViewed']);
        Event::listen(CompanyNameChangeRequested::class, [RecordCompanyAuditEntries::class, 'onCompanyNameChangeRequested']);
        Event::listen(CompanyNameChangeCancelled::class, [RecordCompanyAuditEntries::class, 'onCompanyNameChangeCancelled']);
        Event::listen(CompanyNameChangeDecided::class, [RecordCompanyAuditEntries::class, 'onCompanyNameChangeDecided']);
        Event::listen(CompanyNameChangeDocumentViewed::class, [RecordCompanyAuditEntries::class, 'onCompanyNameChangeDocumentViewed']);
        Event::listen(CompanyNameChangeDecided::class, NotifyCompanyAdminsOfNameChange::class);
        Event::listen(DocumentTypeCreated::class, [RecordCompanyAuditEntries::class, 'onDocumentTypeCreated']);
        Event::listen(DocumentTypeVerificationRulesChanged::class, [RecordCompanyAuditEntries::class, 'onDocumentTypeVerificationRulesChanged']);

        parent::boot();
    }
}
