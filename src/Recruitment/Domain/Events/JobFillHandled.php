<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A team member used "mark as filled" on a job: whether they closed it and how many applications were rejected or skipped. Audited with the counts only. */
final readonly class JobFillHandled implements DomainEvent
{
    public function __construct(
        public int $jobId,
        public int $companyId,
        public int $actorId,
        public bool $closed,
        public int $rejected,
        public int $skipped,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
