<?php

namespace Src\Job\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * A job moved from one status to another. `change` names why: `published`, `closed`, `reopened`, `sent_for_review` (a core edit of a live job) or
 * `expired` (the deadline passed, done by the system, so `changedByUserId` is null). The admin decisions get their own events with the admin part.
 */
final readonly class JobStatusChanged implements DomainEvent
{
    public const string PUBLISHED = 'published';

    public const string CLOSED = 'closed';

    public const string REOPENED = 'reopened';

    public const string SENT_FOR_REVIEW = 'sent_for_review';

    public const string EXPIRED = 'expired';

    public function __construct(
        public int $jobId,
        public int $companyId,
        public string $change,
        public string $from,
        public string $to,
        public ?int $changedByUserId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
