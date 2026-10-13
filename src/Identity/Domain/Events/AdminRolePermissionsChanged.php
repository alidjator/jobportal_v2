<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An operator replaced the permissions of an admin role from the console. `operator` is the operating-system user who ran the command. */
final readonly class AdminRolePermissionsChanged implements DomainEvent
{
    /**
     * @param  list<string>  $added
     * @param  list<string>  $removed
     */
    public function __construct(
        public string $role,
        public array $added,
        public array $removed,
        public string $operator,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
