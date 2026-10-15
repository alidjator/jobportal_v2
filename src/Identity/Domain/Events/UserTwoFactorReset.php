<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An operator removed the second factor of a user (a company member or a psychologist) from the console (a lost device). */
final readonly class UserTwoFactorReset implements DomainEvent
{
    public function __construct(
        public int $userId,
        public string $operator,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
