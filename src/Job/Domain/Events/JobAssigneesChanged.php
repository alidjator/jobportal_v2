<?php

namespace Src\Job\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * The people assigned to a job changed. `changedByUserId` is the company admin who did it, or null when the system did it (a removed member's jobs
 * were handed to the company admins). The lists hold account ids, never names or emails.
 */
final readonly class JobAssigneesChanged implements DomainEvent
{
    /**
     * @param  list<int>  $added
     * @param  list<int>  $removed
     * @param  list<int>  $rolesChanged  the assignees that stay but whose role on the job changed
     */
    public function __construct(
        public int $jobId,
        public int $companyId,
        public ?int $changedByUserId,
        public array $added,
        public array $removed,
        public array $rolesChanged,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
