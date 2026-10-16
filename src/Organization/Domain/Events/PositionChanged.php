<?php

namespace Src\Organization\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A position was created, changed (a new dated version) or changed in status: `change` is `created`, `changed`, `deactivated` or `reactivated`. A dated change keeps the version before and after and, when dated in the past, the reason. */
final readonly class PositionChanged implements DomainEvent
{
    public function __construct(
        public int $groupId,
        public int $legalEntityId,
        public int $positionId,
        public string $change,
        public ?string $validFrom,
        public ?string $reason,
        /** @var array<string, mixed>|null */
        public ?array $before,
        /** @var array<string, mixed>|null */
        public ?array $after,
        public int $actorId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
