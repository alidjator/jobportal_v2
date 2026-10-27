<?php

namespace Src\Assessment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** The platform admin approved or rejected a licence submission. `kind` is `str` or `silp`; `userId` is the psychologist's account. */
final readonly class LicenceDecided implements DomainEvent
{
    public function __construct(
        public int $submissionId,
        public int $psychologistId,
        public int $userId,
        public string $kind,
        public bool $approved,
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
