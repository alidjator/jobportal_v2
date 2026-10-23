<?php

namespace Src\Assessment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** The result of a skill test is final: every answer has its score, so the percentage no longer waits for an assessor. Raised on submitting and when an assessor scores the last answer. */
final readonly class ResultFinalized implements DomainEvent
{
    public function __construct(
        public int $invitationId,
        public int $applicationId,
        public int $jobId,
        public int $companyId,
        public float $percentage,
        public string $integrityTier,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
