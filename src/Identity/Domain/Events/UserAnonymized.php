<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * The personal data of a user was erased. Other contexts listen to it and erase the data they hold for this user
 * (for example Notification: device tokens and notifications).
 */
final readonly class UserAnonymized implements DomainEvent
{
    public function __construct(
        public int $userId,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
