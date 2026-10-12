<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** The recording of a phone call stopped: its file must now be fetched from the voice server and stored. */
final readonly class VoiceRecordingStopped implements DomainEvent
{
    public function __construct(public int $recordingId, private DateTimeImmutable $occurredAt) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
