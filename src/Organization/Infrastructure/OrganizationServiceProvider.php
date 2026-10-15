<?php

namespace Src\Organization\Infrastructure;

use Illuminate\Support\Facades\Event;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Organization\Domain\Events\GroupCreated;
use Src\Organization\Domain\Events\GroupRestored;
use Src\Organization\Domain\Events\GroupRoleChanged;
use Src\Organization\Domain\Events\GroupSuspended;
use Src\Organization\Domain\Events\LegalEntityChanged;
use Src\Organization\Domain\Events\LegalEntityCreated;
use Src\Organization\Domain\Events\TenantLinkedToLegalEntity;
use Src\Organization\Domain\Events\TenantUnlinkedFromLegalEntity;
use Src\Organization\Domain\GroupMemberRepository;
use Src\Organization\Domain\GroupRepository;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Organization\Infrastructure\Listeners\RecordOrganizationAuditEntries;
use Src\Organization\Infrastructure\Listeners\RemoveGroupRoleWhenMemberRemoved;
use Src\Organization\Infrastructure\Persistence\DatabaseGroupMemberRepository;
use Src\Organization\Infrastructure\Persistence\DatabaseGroupRepository;
use Src\Organization\Infrastructure\Persistence\DatabaseLegalEntityRepository;
use Src\Shared\Infrastructure\ContextServiceProvider;

/** The Organization context: customer groups, their legal entities, the link to the tenants of the portal and the group roles (`docs/design/erp-organization.md`). */
final class OrganizationServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        GroupRepository::class => DatabaseGroupRepository::class,
        LegalEntityRepository::class => DatabaseLegalEntityRepository::class,
        GroupMemberRepository::class => DatabaseGroupMemberRepository::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Event::listen(CompanyMemberRemoved::class, RemoveGroupRoleWhenMemberRemoved::class);

        Event::listen(GroupCreated::class, [RecordOrganizationAuditEntries::class, 'onGroupCreated']);
        Event::listen(GroupSuspended::class, [RecordOrganizationAuditEntries::class, 'onGroupSuspended']);
        Event::listen(GroupRestored::class, [RecordOrganizationAuditEntries::class, 'onGroupRestored']);
        Event::listen(LegalEntityCreated::class, [RecordOrganizationAuditEntries::class, 'onLegalEntityCreated']);
        Event::listen(LegalEntityChanged::class, [RecordOrganizationAuditEntries::class, 'onLegalEntityChanged']);
        Event::listen(TenantLinkedToLegalEntity::class, [RecordOrganizationAuditEntries::class, 'onTenantLinked']);
        Event::listen(TenantUnlinkedFromLegalEntity::class, [RecordOrganizationAuditEntries::class, 'onTenantUnlinked']);
        Event::listen(GroupRoleChanged::class, [RecordOrganizationAuditEntries::class, 'onGroupRoleChanged']);
    }
}
