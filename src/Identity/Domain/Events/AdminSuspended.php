<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An operator suspended an administrator from the console; every token of the administrator was revoked. `operator` is the operating-system user who ran the command. */
final readonly class AdminSuspended implements DomainEvent
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
