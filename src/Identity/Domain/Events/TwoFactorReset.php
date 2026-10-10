<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An operator removed the second factor of an administrator from the console (a lost device). */
final readonly class TwoFactorReset implements DomainEvent
{
    public function __construct(
        public int $adminId,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
