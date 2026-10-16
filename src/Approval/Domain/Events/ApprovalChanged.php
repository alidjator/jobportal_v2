<?php

namespace Src\Approval\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * Something happened to a running approval, for the audit trail and later the notifications: `change` is `submitted`, `step_approved`, `granted`, `rejected`, `returned`,
 * `cancelled`, `approver_vacated`, `approver_replaced`, `delegation_created` or `delegation_removed`. `approvalId` is null for a delegation.
 */
final readonly class ApprovalChanged implements DomainEvent
{
    /** @param  array<string, mixed>  $detail */
    public function __construct(
        public ?int $approvalId,
        public ?string $subjectType,
        public ?int $subjectId,
        public int $companyId,
        public string $change,
        public ?int $actorId,
        public array $detail,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
