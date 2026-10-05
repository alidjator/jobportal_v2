<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

final readonly class CompanyMemberRemoved implements DomainEvent
{
    public function __construct(
        public int $companyId,
        public int $userId,
        public string $role,
        public int $removedByUserId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
