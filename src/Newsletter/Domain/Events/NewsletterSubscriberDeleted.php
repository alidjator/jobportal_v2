<?php

namespace Src\Newsletter\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An admin deleted a subscriber (an erasure on request). The address is not in the event. */
final readonly class NewsletterSubscriberDeleted implements DomainEvent
{
    public function __construct(
        public int $subscriberId,
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
