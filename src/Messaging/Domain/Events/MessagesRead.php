<?php

namespace Src\Messaging\Domain\Events;

use DateTimeImmutable;
use Src\Messaging\Domain\SenderSide;
use Src\Shared\Domain\DomainEvent;

/** A participant read a conversation up to the message (so the other side can show it as read). */
final readonly class MessagesRead implements DomainEvent
{
    public function __construct(
        public int $conversationId,
        public SenderSide $side,
        public int $userId,
        public ?int $lastReadMessageId,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
