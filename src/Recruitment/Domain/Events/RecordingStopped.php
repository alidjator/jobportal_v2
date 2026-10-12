<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A recording stopped (by a member or because the room closed): its raw files must now be converted. */
final readonly class RecordingStopped implements DomainEvent
{
    public function __construct(public int $recordingId, private DateTimeImmutable $occurredAt) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
