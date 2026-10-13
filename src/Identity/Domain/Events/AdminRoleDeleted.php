<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An operator deleted an admin role that no administrator held, from the console. `operator` is the operating-system user who ran the command. */
final readonly class AdminRoleDeleted implements DomainEvent
{
    public function __construct(
        public string $role,
        public string $operator,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
