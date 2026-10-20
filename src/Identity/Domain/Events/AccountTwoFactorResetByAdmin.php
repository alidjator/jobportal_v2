<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A platform admin removed the second factor of a job seeker or company member after an identity check. The Company context uses it to leave an in-app notice for a company member. */
final readonly class AccountTwoFactorResetByAdmin implements DomainEvent
{
    public function __construct(
        public int $userId,
        public int $adminId,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
