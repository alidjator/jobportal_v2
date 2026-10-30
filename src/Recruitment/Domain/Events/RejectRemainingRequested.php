<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** The team asked to reject the applications still active in some stages of a closed job (`docs/design/phase-8-candidate-experience.md` section 1): a queued step rejects them one by one. */
final readonly class RejectRemainingRequested implements DomainEvent
{
    /** @param  list<int>  $stageIds */
    public function __construct(
        public int $jobId,
        public int $companyId,
        public int $requestedByUserId,
        public array $stageIds,
        public string $reason,
        public ?string $note,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
