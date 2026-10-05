<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A company lost the verification badge because the list of document types changed (not when the company replaced an approved document itself). */
final readonly class CompanyVerificationWithdrawn implements DomainEvent
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
