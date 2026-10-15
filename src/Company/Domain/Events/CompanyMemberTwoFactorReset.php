<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A company admin removed the second factor of a member who lost the device; the member enrols a new one at the next sign-in. */
final readonly class CompanyMemberTwoFactorReset implements DomainEvent
{
    public function __construct(
        public int $companyId,
        public int $userId,
        public int $resetByUserId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
