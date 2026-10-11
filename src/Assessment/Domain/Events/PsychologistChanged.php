<?php

namespace Src\Assessment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An admin changed a psychologist: `change` is `credentials_changed`, `suspended`, `restored` or `two_factor_reset`. */
final readonly class PsychologistChanged implements DomainEvent
{
    public function __construct(
        public int $psychologistId,
        public string $change,
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
