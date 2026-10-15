<?php

namespace Src\Organization\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A group role was `granted`, `changed` or `removed`; `permissions` are the ones after the change (empty when removed). */
final readonly class GroupRoleChanged implements DomainEvent
{
    public function __construct(
        public int $groupId,
        public int $userId,
        public string $change,
        /** @var list<string> */
        public array $permissions,
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
