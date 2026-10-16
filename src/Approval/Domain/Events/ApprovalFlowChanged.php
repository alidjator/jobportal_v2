<?php

namespace Src\Approval\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An approval flow was `saved` (created or replaced) or `removed`; `ownerType` is `group` or `company`. */
final readonly class ApprovalFlowChanged implements DomainEvent
{
    public function __construct(
        public string $ownerType,
        public int $ownerId,
        public string $subjectType,
        public string $change,
        public int $stepCount,
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
