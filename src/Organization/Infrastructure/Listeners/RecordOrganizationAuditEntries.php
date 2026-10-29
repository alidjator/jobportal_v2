<?php

namespace Src\Organization\Infrastructure\Listeners;

use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Organization\Domain\Events\GroupCreated;
use Src\Organization\Domain\Events\GroupRecruitmentRead;
use Src\Organization\Domain\Events\GroupRestored;
use Src\Organization\Domain\Events\GroupRoleChanged;
use Src\Organization\Domain\Events\GroupSuspended;
use Src\Organization\Domain\Events\LegalEntityChanged;
use Src\Organization\Domain\Events\LegalEntityCreated;
use Src\Organization\Domain\Events\PositionChanged;
use Src\Organization\Domain\Events\StructureItemChanged;
use Src\Organization\Domain\Events\SupportAccessChanged;
use Src\Organization\Domain\Events\SupportRead;
use Src\Organization\Domain\Events\TenantLinkedToLegalEntity;
use Src\Organization\Domain\Events\TenantUnlinkedFromLegalEntity;
use Src\Organization\Domain\Events\UnitRestructured;
use Src\Shared\Application\Accounts\AdminAccounts;
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

    public function __construct(private readonly AuditTrail $trail, private readonly AdminAccounts $admins) {}

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

    public function onStructureItemChanged(StructureItemChanged $event): void
    {
        $action = match ($event->kind) {
            'grade' => ['created' => AuditAction::GROUP_GRADE_CREATED, 'updated' => AuditAction::GROUP_GRADE_UPDATED, 'deactivated' => AuditAction::GROUP_GRADE_DEACTIVATED, 'reactivated' => AuditAction::GROUP_GRADE_REACTIVATED],
            'unit_type' => [
                'created' => AuditAction::GROUP_UNIT_TYPE_CREATED, 'updated' => AuditAction::GROUP_UNIT_TYPE_UPDATED, 'deactivated' => AuditAction::GROUP_UNIT_TYPE_DEACTIVATED,
                'reactivated' => AuditAction::GROUP_UNIT_TYPE_REACTIVATED,
            ],
            default => [
                'created' => AuditAction::GROUP_COST_CENTRE_CREATED, 'updated' => AuditAction::GROUP_COST_CENTRE_UPDATED, 'deactivated' => AuditAction::GROUP_COST_CENTRE_DEACTIVATED,
                'reactivated' => AuditAction::GROUP_COST_CENTRE_REACTIVATED,
            ],
        };

        $this->record($event->actorId, false, $action[$event->change], $event->groupId, ['kind' => $event->kind, 'item_id' => $event->itemId], $event->ipAddress, $event->userAgent, $event->occurredAt());
    }

    public function onUnitRestructured(UnitRestructured $event): void
    {
        $action = match ($event->change) {
            'created' => AuditAction::GROUP_UNIT_CREATED,
            'deactivated' => AuditAction::GROUP_UNIT_DEACTIVATED,
            'reactivated' => AuditAction::GROUP_UNIT_REACTIVATED,
            'reordered' => AuditAction::GROUP_UNITS_REORDERED,
            default => AuditAction::GROUP_UNIT_RESTRUCTURED,
        };

        $this->record(
            $event->actorId, false, $action, $event->groupId,
            ['legal_entity_id' => $event->legalEntityId, 'unit_id' => $event->unitId, 'valid_from' => $event->validFrom, 'reason' => $event->reason, 'before' => $event->before, 'after' => $event->after],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        );
    }

    public function onPositionChanged(PositionChanged $event): void
    {
        $action = match ($event->change) {
            'created' => AuditAction::GROUP_POSITION_CREATED,
            'deactivated' => AuditAction::GROUP_POSITION_DEACTIVATED,
            'reactivated' => AuditAction::GROUP_POSITION_REACTIVATED,
            default => AuditAction::GROUP_POSITION_CHANGED,
        };

        $this->record(
            $event->actorId, false, $action, $event->groupId,
            ['legal_entity_id' => $event->legalEntityId, 'position_id' => $event->positionId, 'valid_from' => $event->validFrom, 'reason' => $event->reason, 'before' => $event->before, 'after' => $event->after],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        );
    }

    public function onSupportAccessChanged(SupportAccessChanged $event): void
    {
        $this->record(
            $event->actorId, false, $event->change === 'granted' ? AuditAction::GROUP_SUPPORT_GRANTED : AuditAction::GROUP_SUPPORT_REVOKED, $event->groupId,
            ['grant_id' => $event->grantId, 'admin_id' => $event->adminId, 'admin_name' => $this->adminName($event->adminId), 'expires_at' => $event->expiresAt],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        );
    }

    /** A read under support access is the platform admin's action: the group sees which admin read what, never the admin's address. */
    public function onSupportRead(SupportRead $event): void
    {
        $this->record(
            $event->adminId, true, AuditAction::GROUP_SUPPORT_READ, $event->groupId, ['admin_name' => $this->adminName($event->adminId), 'request' => $event->request],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        );
    }

    /** Reading applicants of a tenant is audited for the group and for the tenant: its own admins see which group member read what in the company activity log. */
    public function onGroupRecruitmentRead(GroupRecruitmentRead $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->userId, AuditAction::GROUP_RECRUITMENT_READ, self::SUBJECT_TYPE, $event->groupId,
            ['group_id' => $event->groupId, 'company_id' => $event->companyId, 'request' => $event->request], $event->ipAddress, $event->userAgent, $event->occurredAt(), $event->companyId,
        ));
    }

    private function adminName(int $adminId): ?string
    {
        return ($this->admins->summaries([$adminId])[$adminId] ?? null)?->name;
    }

    /** @param  array<string, mixed>  $metadata */
    private function record(int $actorId, bool $byAdmin, string $action, int $groupId, array $metadata, ?string $ipAddress, ?string $userAgent, DateTimeImmutable $at): void
    {
        $this->trail->record(new AuditEntry(
            $byAdmin ? AuditActorType::Admin : AuditActorType::User, $actorId, $action, self::SUBJECT_TYPE, $groupId, ['group_id' => $groupId, ...$metadata], $ipAddress, $userAgent, $at,
        ));
    }
}
