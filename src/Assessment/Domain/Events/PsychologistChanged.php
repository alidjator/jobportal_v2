<?php

namespace Src\Assessment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An admin (`adminId`) or a lead of the institution (`userId`, their account) changed a psychologist: `change` is `credentials_changed`, `role_changed`, `suspended`, `restored` or `two_factor_reset`. */
final readonly class PsychologistChanged implements DomainEvent
{
    public function __construct(
        public int $psychologistId,
        public string $change,
        public ?int $adminId,
        public ?int $userId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
