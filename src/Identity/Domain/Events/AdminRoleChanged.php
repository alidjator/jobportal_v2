<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An operator gave an administrator another role from the console. `operator` is the operating-system user who ran the command. */
final readonly class AdminRoleChanged implements DomainEvent
{
    public function __construct(
        public int $adminId,
        public ?string $previousRole,
        public string $role,
        public string $operator,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
