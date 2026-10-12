<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** The video server finished converting a recording: its file must now be moved to the private disk. */
final readonly class RecordingConverted implements DomainEvent
{
    public function __construct(public int $recordingId, private DateTimeImmutable $occurredAt) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
