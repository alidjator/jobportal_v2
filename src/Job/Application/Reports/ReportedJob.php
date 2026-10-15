<?php

namespace Src\Job\Application\Reports;

use DateTimeImmutable;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Company\CompanySummary;

/** One row of the admin list of reported jobs: the job with its open reports counted. `reasons` maps a reason to how many open reports gave it. */
final readonly class ReportedJob
{
    /** @param  array<string, int>  $reasons */
    public function __construct(
        public int $jobId,
        public string $title,
        public JobStatus $status,
        public int $companyId,
        public ?CompanySummary $company,
        public int $openCount,
        public array $reasons,
        public DateTimeImmutable $firstReportedAt,
        public DateTimeImmutable $lastReportedAt,
        public ?DateTimeImmutable $heldAt,
    ) {}

    public function withCompany(?CompanySummary $company): self
    {
        return new self($this->jobId, $this->title, $this->status, $this->companyId, $company, $this->openCount, $this->reasons, $this->firstReportedAt, $this->lastReportedAt, $this->heldAt);
    }
}
