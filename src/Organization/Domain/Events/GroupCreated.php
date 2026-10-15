<?php

namespace Src\Organization\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A platform admin created a group with its first legal entity. */
final readonly class GroupCreated implements DomainEvent
{
    public function __construct(
        public int $groupId,
        public int $legalEntityId,
        public string $name,
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
