<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An operator created an admin role from the console. `operator` is the operating-system user who ran the command. */
final readonly class AdminRoleCreated implements DomainEvent
{
    /** @param  list<string>  $permissions */
    public function __construct(
        public string $role,
        public array $permissions,
        public string $operator,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
