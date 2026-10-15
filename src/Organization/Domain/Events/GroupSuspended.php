<?php

namespace Src\Organization\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A platform admin suspended a group; its internal tenants were suspended with it. */
final readonly class GroupSuspended implements DomainEvent
{
    public function __construct(
        public int $groupId,
        public string $reason,
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
