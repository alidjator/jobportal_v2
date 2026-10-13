<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An operator reactivated a suspended administrator from the console. `operator` is the operating-system user who ran the command. */
final readonly class AdminReactivated implements DomainEvent
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
