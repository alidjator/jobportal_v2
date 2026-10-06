<?php

namespace Src\Job\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * A platform admin decided about a job: `approved` or `rejected` (from review), `taken_down` (a live job) or `restored` (a taken-down job). The reason is
 * given for a rejection and a take-down only. The admin's name is never part of what the company is told.
 */
final readonly class JobDecided implements DomainEvent
{
    public const string APPROVED = 'approved';

    public const string REJECTED = 'rejected';

    public const string TAKEN_DOWN = 'taken_down';

    public const string RESTORED = 'restored';

    public function __construct(
        public int $jobId,
        public int $companyId,
        public string $decision,
        public string $from,
        public string $to,
        public ?string $reason,
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
