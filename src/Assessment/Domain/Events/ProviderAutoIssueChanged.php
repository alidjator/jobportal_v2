<?php

namespace Src\Assessment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A platform admin allowed or stopped the automatic issue of reports for a provider. */
final readonly class ProviderAutoIssueChanged implements DomainEvent
{
    public function __construct(
        public int $providerId,
        public bool $allowed,
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
