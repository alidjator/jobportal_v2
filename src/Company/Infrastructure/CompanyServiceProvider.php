<?php

namespace Src\Company\Infrastructure;

use Src\Company\Application\CompanyEmailTemplates;
use Src\Company\Application\CompanyPermissions;
use Src\Company\Application\CompanySlugGenerator;
use Src\Company\Application\Invitations\InvitationLinkBuilder;
use Src\Company\Application\Invitations\InvitationSender;
use Src\Company\Application\Invitations\InvitationThrottle;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\InvitationRepository;
use Src\Company\Infrastructure\Mail\MailInvitationSender;
use Src\Company\Infrastructure\Persistence\DatabaseCompanyMemberRepository;
use Src\Company\Infrastructure\Persistence\DatabaseCompanyRepository;
use Src\Company\Infrastructure\Persistence\DatabaseInvitationRepository;
use Src\Company\Infrastructure\Persistence\MemberRepositoryMembershipResolver;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class CompanyServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CompanyRepository::class => DatabaseCompanyRepository::class,
        CompanyMemberRepository::class => DatabaseCompanyMemberRepository::class,
        InvitationRepository::class => DatabaseInvitationRepository::class,
        CompanyMembershipResolver::class => MemberRepositoryMembershipResolver::class,
        CompanySlugGenerator::class => LaravelCompanySlugGenerator::class,
        InvitationLinkBuilder::class => ConfiguredInvitationLinkBuilder::class,
        InvitationThrottle::class => RateLimiterInvitationThrottle::class,
        InvitationSender::class => MailInvitationSender::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...CompanyPermissions::definitions());
        });

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...CompanyEmailTemplates::definitions());
        });
    }
}
