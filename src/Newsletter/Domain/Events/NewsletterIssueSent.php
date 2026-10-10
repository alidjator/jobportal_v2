<?php

namespace Src\Newsletter\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An admin sent an issue to the confirmed subscribers. */
final readonly class NewsletterIssueSent implements DomainEvent
{
    public function __construct(
        public int $issueId,
        public int $adminId,
        public int $recipients,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
