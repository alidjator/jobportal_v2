<?php

namespace Src\Organization\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A unit was created, restructured (a new dated version), changed in status or put in a new order: `change` is `created`, `restructured`, `deactivated`, `reactivated` or `reordered` (some units put in a new order: `before` and `after` hold their ids). A dated change keeps the version before and after and, when dated in the past, the reason. */
final readonly class UnitRestructured implements DomainEvent
{
    public function __construct(
        public int $groupId,
        public int $legalEntityId,
        public int $unitId,
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
