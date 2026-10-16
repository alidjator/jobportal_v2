<?php

namespace Src\Approval\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Approval\Domain\Events\ApprovalFlowChanged;
use Src\Approval\Domain\OwnerType;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Writes the audit entries of the Approval context. Runs on the `default` queue. A group flow belongs to the group's activity log, a tenant flow to the tenant's (and so to its group's). */
final class RecordApprovalAuditEntries implements ShouldQueue
{
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

    public function onFlowChanged(ApprovalFlowChanged $event): void
    {
        $group = $event->ownerType === OwnerType::Group->value;

        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->actorId, $event->change === 'saved' ? AuditAction::APPROVAL_FLOW_SAVED : AuditAction::APPROVAL_FLOW_REMOVED,
            $group ? 'organization_group' : 'company', $event->ownerId,
            ['owner_type' => $event->ownerType, 'owner_id' => $event->ownerId, 'subject_type' => $event->subjectType, 'steps' => $event->stepCount],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
            $group ? null : $event->ownerId, $group ? $event->ownerId : null,
        ));
    }
}
