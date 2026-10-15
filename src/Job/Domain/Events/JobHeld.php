<?php

namespace Src\Job\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** Enough job seekers reported a live job, so it left the public view until an admin decides. The company is told that, never who reported or why. */
final readonly class JobHeld implements DomainEvent
{
    public function __construct(
        public int $jobId,
        public int $companyId,
        public int $reporters,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
