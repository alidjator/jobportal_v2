<?php

namespace Src\Assessment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A psychologist downloaded the report register as a file. `filters` holds the days and the ids that narrowed it (no text), `rowCount` how many lines it held. */
final readonly class ReportRegisterExported implements DomainEvent
{
    /** @param  array<string, string|int|null>  $filters */
    public function __construct(
        public int $providerId,
        public int $psychologistUserId,
        public array $filters,
        public int $rowCount,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
