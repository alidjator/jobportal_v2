<?php

namespace Src\Job\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A company admin added a member to several jobs at once. Holds the count only, never job titles; the per-job changes are announced by `JobAssigneesChanged`. */
final readonly class MemberAssignedToJobs implements DomainEvent
{
    public function __construct(
        public int $companyId,
        public int $userId,
        public int $assignedByUserId,
        public int $count,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
