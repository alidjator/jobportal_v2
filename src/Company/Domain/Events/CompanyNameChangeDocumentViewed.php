<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An admin opened the proof of a name change request. */
final readonly class CompanyNameChangeDocumentViewed implements DomainEvent
{
    public function __construct(
        public int $companyId,
        public int $requestId,
        public int $adminId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
