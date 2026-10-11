<?php

namespace Src\Assessment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A candidate submitted a test, or the time ran out and the server submitted it. */
final readonly class AttemptSubmitted implements DomainEvent
{
    public function __construct(
        public int $invitationId,
        public int $applicationId,
        public int $jobId,
        public int $companyId,
        public bool $auto,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
