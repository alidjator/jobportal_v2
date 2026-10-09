<?php

namespace Src\Messaging\Domain\Events;

use DateTimeImmutable;
use Src\Messaging\Domain\SenderSide;
use Src\Shared\Domain\DomainEvent;

/** A message was sent. `bulk` is true for one that came from a bulk send (its notifications run on the `bulk` queue). */
final readonly class MessageSent implements DomainEvent
{
    public function __construct(
        public int $conversationId,
        public int $messageId,
        public int $companyId,
        public int $jobSeekerUserId,
        public SenderSide $side,
        public int $senderUserId,
        public bool $bulk,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
