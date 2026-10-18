<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A hire brought a job's hires up to its vacancies. The job's assignees are told, so the team can close it and reject the rest. */
final readonly class JobFilled implements DomainEvent
{
    public function __construct(
        public int $jobId,
        public int $companyId,
        public int $hires,
        public int $vacancies,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
