<?php

namespace Src\Company\Infrastructure;

use Illuminate\Support\Facades\Event;
use Src\Company\Application\Admin\AdminCompanyReader;
use Src\Company\Application\CompanyEmailTemplates;
use Src\Company\Application\CompanyPermissions;
use Src\Company\Application\CompanySlugGenerator;
use Src\Company\Application\Images\CompanySettings;
use Src\Company\Application\Invitations\InvitationLinkBuilder;
use Src\Company\Application\Invitations\InvitationSender;
use Src\Company\Application\Invitations\InvitationThrottle;
use Src\Company\Application\Verification\CompanyVerificationBadge;
use Src\Company\Domain\CompanyDocumentRepository;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\DocumentTypeRepository;
use Src\Company\Domain\Events\CompanyDocumentApproved;
use Src\Company\Domain\Events\CompanyDocumentRejected;
use Src\Company\Domain\Events\CompanyVerificationGranted;
use Src\Company\Domain\Events\CompanyVerificationWithdrawn;
use Src\Company\Domain\InvitationRepository;
use Src\Company\Infrastructure\Listeners\NotifyCompanyAdminsOfVerification;
use Src\Company\Infrastructure\Mail\MailInvitationSender;
use Src\Company\Infrastructure\Persistence\DatabaseAdminCompanyReader;
use Src\Company\Infrastructure\Persistence\DatabaseCompanyDocumentRepository;
use Src\Company\Infrastructure\Persistence\DatabaseCompanyMemberRepository;
use Src\Company\Infrastructure\Persistence\DatabaseCompanyRepository;
use Src\Company\Infrastructure\Persistence\DatabaseCompanyVerificationBadge;
use Src\Company\Infrastructure\Persistence\DatabaseDocumentTypeRepository;
use Src\Company\Infrastructure\Persistence\DatabaseInvitationRepository;
use Src\Company\Infrastructure\Persistence\MemberRepositoryMembershipResolver;
use Src\Company\Infrastructure\Persistence\RepositoryCompanyDirectory;
use Src\Company\Infrastructure\Persistence\RepositoryCompanyTeam;
use Src\Company\Infrastructure\Persistence\RepositoryPublicCompanies;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class CompanyServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CompanyRepository::class => DatabaseCompanyRepository::class,
        CompanyMemberRepository::class => DatabaseCompanyMemberRepository::class,
        AdminCompanyReader::class => DatabaseAdminCompanyReader::class,
        CompanyDirectory::class => RepositoryCompanyDirectory::class,
        CompanyTeam::class => RepositoryCompanyTeam::class,
        PublicCompanies::class => RepositoryPublicCompanies::class,
        InvitationRepository::class => DatabaseInvitationRepository::class,
        CompanyMembershipResolver::class => MemberRepositoryMembershipResolver::class,
        CompanySlugGenerator::class => LaravelCompanySlugGenerator::class,
        InvitationLinkBuilder::class => ConfiguredInvitationLinkBuilder::class,
        InvitationThrottle::class => RateLimiterInvitationThrottle::class,
        InvitationSender::class => MailInvitationSender::class,
        DocumentTypeRepository::class => DatabaseDocumentTypeRepository::class,
        CompanyDocumentRepository::class => DatabaseCompanyDocumentRepository::class,
        CompanyVerificationBadge::class => DatabaseCompanyVerificationBadge::class,
    ];

    public function register(): void
    {
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
        Event::listen(CompanyDocumentApproved::class, [NotifyCompanyAdminsOfVerification::class, 'onDocumentApproved']);
        Event::listen(CompanyDocumentRejected::class, [NotifyCompanyAdminsOfVerification::class, 'onDocumentRejected']);
        Event::listen(CompanyVerificationGranted::class, [NotifyCompanyAdminsOfVerification::class, 'onVerificationGranted']);
        Event::listen(CompanyVerificationWithdrawn::class, [NotifyCompanyAdminsOfVerification::class, 'onVerificationWithdrawn']);

        parent::boot();
    }
}
