<?php

namespace Src\Organization\Infrastructure\Listeners;

use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Organization\Domain\Events\GroupCreated;
use Src\Organization\Domain\Events\GroupRestored;
use Src\Organization\Domain\Events\GroupRoleChanged;
use Src\Organization\Domain\Events\GroupSuspended;
use Src\Organization\Domain\Events\LegalEntityChanged;
use Src\Organization\Domain\Events\LegalEntityCreated;
use Src\Organization\Domain\Events\TenantLinkedToLegalEntity;
use Src\Organization\Domain\Events\TenantUnlinkedFromLegalEntity;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Writes the audit entries of the Organization context: the group, its legal entities, the tenant links and the group roles. Runs on the `default` queue. */
final class RecordOrganizationAuditEntries implements ShouldQueue
{
    private const string SUBJECT_TYPE = 'organization_group';

    public int $tries = 3;

    public function __construct(private readonly AuditTrail $trail) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function onGroupCreated(GroupCreated $event): void
    {
        $this->record($event->actorId, $event->actorIsAdmin, AuditAction::GROUP_CREATED, $event->groupId, ['legal_entity_id' => $event->legalEntityId], $event->ipAddress, $event->userAgent, $event->occurredAt());
    }

    public function onGroupSuspended(GroupSuspended $event): void
    {
        $this->record($event->actorId, $event->actorIsAdmin, AuditAction::GROUP_SUSPENDED, $event->groupId, ['reason' => $event->reason, 'tenants' => $event->tenantCount], $event->ipAddress, $event->userAgent, $event->occurredAt());
    }

    public function onGroupRestored(GroupRestored $event): void
    {
        $this->record($event->actorId, $event->actorIsAdmin, AuditAction::GROUP_RESTORED, $event->groupId, ['tenants' => $event->tenantCount], $event->ipAddress, $event->userAgent, $event->occurredAt());
    }

    public function onLegalEntityCreated(LegalEntityCreated $event): void
    {
        $this->record($event->actorId, $event->actorIsAdmin, AuditAction::GROUP_LEGAL_ENTITY_CREATED, $event->groupId, ['legal_entity_id' => $event->legalEntityId], $event->ipAddress, $event->userAgent, $event->occurredAt());
    }

    public function onLegalEntityChanged(LegalEntityChanged $event): void
    {
        $action = match ($event->change) {
            'deactivated' => AuditAction::GROUP_LEGAL_ENTITY_DEACTIVATED,
            'reactivated' => AuditAction::GROUP_LEGAL_ENTITY_REACTIVATED,
            default => AuditAction::GROUP_LEGAL_ENTITY_UPDATED,
        };

        $this->record($event->actorId, $event->actorIsAdmin, $action, $event->groupId, ['legal_entity_id' => $event->legalEntityId], $event->ipAddress, $event->userAgent, $event->occurredAt());
    }

    public function onTenantLinked(TenantLinkedToLegalEntity $event): void
    {
        $this->record($event->actorId, $event->actorIsAdmin, AuditAction::GROUP_TENANT_LINKED, $event->groupId, ['legal_entity_id' => $event->legalEntityId, 'company_id' => $event->companyId], $event->ipAddress, $event->userAgent, $event->occurredAt());
    }

    public function onTenantUnlinked(TenantUnlinkedFromLegalEntity $event): void
    {
        $this->record($event->actorId, $event->actorIsAdmin, AuditAction::GROUP_TENANT_UNLINKED, $event->groupId, ['legal_entity_id' => $event->legalEntityId, 'company_id' => $event->companyId], $event->ipAddress, $event->userAgent, $event->occurredAt());
    }

    public function onGroupRoleChanged(GroupRoleChanged $event): void
    {
        $action = match ($event->change) {
            'granted' => AuditAction::GROUP_ROLE_GRANTED,
            'removed' => AuditAction::GROUP_ROLE_REMOVED,
            default => AuditAction::GROUP_ROLE_CHANGED,
        };

        $this->record($event->actorId, $event->actorIsAdmin, $action, $event->groupId, ['user_id' => $event->userId, 'permissions' => $event->permissions], $event->ipAddress, $event->userAgent, $event->occurredAt());
    }

    /** @param  array<string, mixed>  $metadata */
    private function record(int $actorId, bool $byAdmin, string $action, int $groupId, array $metadata, ?string $ipAddress, ?string $userAgent, DateTimeImmutable $at): void
    {
        $this->trail->record(new AuditEntry(
            $byAdmin ? AuditActorType::Admin : AuditActorType::User, $actorId, $action, self::SUBJECT_TYPE, $groupId, ['group_id' => $groupId, ...$metadata], $ipAddress, $userAgent, $at,
        ));
    }
}
