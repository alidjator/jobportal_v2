<?php

namespace Src\JobSeeker\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A job seeker asked for a copy of their personal data; the archive is built in the background. */
final readonly class DataExportRequested implements DomainEvent
{
    public function __construct(public int $exportId, public int $userId, private DateTimeImmutable $occurredAt) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
