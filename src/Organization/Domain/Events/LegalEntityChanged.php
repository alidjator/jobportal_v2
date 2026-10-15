<?php

namespace Src\Organization\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A legal entity changed: `change` is `updated`, `deactivated` or `reactivated`. */
final readonly class LegalEntityChanged implements DomainEvent
{
    public function __construct(
        public int $groupId,
        public int $legalEntityId,
        public string $change,
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
