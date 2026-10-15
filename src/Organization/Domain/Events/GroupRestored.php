<?php

namespace Src\Organization\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A platform admin restored a group; the tenants its suspension suspended were restored. */
final readonly class GroupRestored implements DomainEvent
{
    public function __construct(
        public int $groupId,
        public int $tenantCount,
        public int $actorId,
        public bool $actorIsAdmin,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
