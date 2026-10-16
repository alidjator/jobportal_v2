<?php

namespace Src\Workforce\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Infrastructure\Queue\QueueName;
use Src\Workforce\Domain\Events\PlanChanged;
use Src\Workforce\Domain\Events\RequisitionChanged;

/** Writes the audit entries of the Workforce context. Runs on the `default` queue. A plan belongs to the log of its tenant (when its legal entity has one) and to its group's. */
final class RecordWorkforceAuditEntries implements ShouldQueue
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

    public function onPlanChanged(PlanChanged $event): void
    {
        $this->trail->record(new AuditEntry(
            $event->actorId === null ? AuditActorType::System : AuditActorType::User, $event->actorId, 'workforce.plan_'.$event->change, 'workforce_plan', $event->planId,
            [
                'legal_entity_id' => $event->legalEntityId, 'fiscal_year' => $event->fiscalYear, 'version' => $event->version,
                ...($event->reason === null ? [] : ['reason' => $event->reason]),
            ],
            $event->ipAddress, $event->userAgent, $event->occurredAt(), $event->companyId, $event->groupId > 0 ? $event->groupId : null,
        ));
    }

    /** A requisition always belongs to a tenant, so it is in the tenant's log and in its group's. */
    public function onRequisitionChanged(RequisitionChanged $event): void
    {
        $this->trail->record(new AuditEntry(
            $event->actorId === null ? AuditActorType::System : AuditActorType::User, $event->actorId, 'workforce.requisition_'.$event->change, 'workforce_requisition', $event->requisitionId,
            $event->detail, $event->ipAddress, $event->userAgent, $event->occurredAt(), $event->companyId, $event->groupId > 0 ? $event->groupId : null,
        ));
    }
}
