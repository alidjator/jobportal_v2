<?php

namespace Src\Job\Application\Reports;

use DateTimeImmutable;
use Src\Job\Domain\JobReportReason;
use Src\Job\Domain\JobReportStatus;

/** One report as an admin reads it. The reporter is null once the account was erased (the note goes with it). */
final readonly class JobReportEntry
{
    public function __construct(
        public int $id,
        public JobReportReason $reason,
        public ?string $note,
        public JobReportStatus $status,
        public ?int $reporterUserId,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $decidedAt,
        public ?int $decidedByAdminId,
    ) {}
}
