<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An operator set a new password for an administrator from the console; every token of the administrator was revoked. `operator` is the operating-system user who ran the command. */
final readonly class AdminPasswordReset implements DomainEvent
{
    public function __construct(
        public int $adminId,
        public string $operator,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
