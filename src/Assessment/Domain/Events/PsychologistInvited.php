<?php

namespace Src\Assessment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An admin invited a psychologist to a provider. */
final readonly class PsychologistInvited implements DomainEvent
{
    public function __construct(
        public int $providerId,
        public int $invitationId,
        public int $adminId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
