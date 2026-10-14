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
        /** The company admin who takes over the jobs of the member; null when the owner does (or the member's account was erased). */
        public ?int $transferToUserId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
