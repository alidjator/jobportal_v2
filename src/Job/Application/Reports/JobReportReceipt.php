<?php

namespace Src\Job\Application\Reports;

use DateTimeImmutable;
use Src\Job\Domain\JobReportReason;

/** What the reporter gets back: the report as it was stored. */
final readonly class JobReportReceipt
{
    public function __construct(
        public int $id,
        public int $jobId,
        public JobReportReason $reason,
        public DateTimeImmutable $createdAt,
    ) {}
}
