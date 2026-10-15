<?php

namespace Src\Organization\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A tenant was unlinked from its legal entity: it is an external tenant again. */
final readonly class TenantUnlinkedFromLegalEntity implements DomainEvent
{
    public function __construct(
        public int $groupId,
        public int $legalEntityId,
        public int $companyId,
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
