<?php

namespace Src\Assessment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An invited psychologist accepted and created their account. */
final readonly class PsychologistJoined implements DomainEvent
{
    public function __construct(
        public int $providerId,
        public int $psychologistId,
        public int $userId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
