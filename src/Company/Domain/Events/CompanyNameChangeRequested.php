<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A company member requested a name change request of their company. */
final readonly class CompanyNameChangeRequested implements DomainEvent
{
    public function __construct(
        public int $companyId,
        public int $requestId,
        public int $userId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
