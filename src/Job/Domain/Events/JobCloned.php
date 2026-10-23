<?php

namespace Src\Job\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A member copied a job into a new draft. Other contexts copy what they keep per job (the default tests of Assessment) to the new one. */
final readonly class JobCloned implements DomainEvent
{
    public function __construct(
        public int $sourceJobId,
        public int $newJobId,
        public int $companyId,
        public int $clonedByUserId,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
