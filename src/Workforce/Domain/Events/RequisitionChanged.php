<?php

namespace Src\Workforce\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * A requisition was `created`, `updated`, `submitted`, `approved`, `rejected`, `returned`, `cancelled`, `closed`, `reduced` (fewer hires, no approval needed), `job_opened`, or its amendment
 * was `amendment_requested`, `amendment_approved`, `amendment_rejected` or `amendment_returned`. `actorId` is null when the system did it.
 */
final readonly class RequisitionChanged implements DomainEvent
{
    /** @param  array<string, mixed>  $detail */
    public function __construct(
        public int $requisitionId,
        public int $companyId,
        public int $groupId,
        public string $change,
        public ?int $actorId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
        public array $detail = [],
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
