<?php

namespace Src\Messaging\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A sender deleted their message. */
final readonly class MessageDeleted implements DomainEvent
{
    public function __construct(public int $conversationId, public int $messageId, private DateTimeImmutable $occurredAt) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
