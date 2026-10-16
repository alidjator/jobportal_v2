<?php

namespace Src\Organization\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A grade, unit type or cost centre changed: `kind` is `grade`, `unit_type` or `cost_centre`, `change` is `created`, `updated`, `deactivated` or `reactivated`. */
final readonly class StructureItemChanged implements DomainEvent
{
    public function __construct(
        public int $groupId,
        public string $kind,
        public int $itemId,
        public string $change,
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
