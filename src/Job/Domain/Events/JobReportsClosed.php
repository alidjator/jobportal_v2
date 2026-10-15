<?php

namespace Src\Job\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * An admin closed the open reports of a job: `dismissed` (nothing wrong) or `actioned` (the job was taken down). `holdReleased` says the job was on hold and
 * is not any more. `reporterIds` are the accounts that reported (not erased), who are told the report was reviewed, without the outcome.
 */
final readonly class JobReportsClosed implements DomainEvent
{
    public const string DISMISSED = 'dismissed';

    public const string ACTIONED = 'actioned';

    /** @param  list<int>  $reporterIds */
    public function __construct(
        public int $jobId,
        public int $companyId,
        public string $outcome,
        public int $closed,
        public bool $holdReleased,
        public array $reporterIds,
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
