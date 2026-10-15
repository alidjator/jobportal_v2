<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A company changed its name through its profile (an unverified company; a verified one goes through a name change request and its decision event). */
final readonly class CompanyRenamed implements DomainEvent
{
    public function __construct(
        public int $companyId,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
