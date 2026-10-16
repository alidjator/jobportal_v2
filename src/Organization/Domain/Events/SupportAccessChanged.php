<?php

namespace Src\Organization\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** Support access to a group was `granted` or `revoked` by a group admin; `expiresAt` is when the grant ends (`Y-m-d H:i:s`, UTC). */
final readonly class SupportAccessChanged implements DomainEvent
{
    public function __construct(
        public int $groupId,
        public int $grantId,
        public int $adminId,
        public string $change,
        public string $expiresAt,
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
