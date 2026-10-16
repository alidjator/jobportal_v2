<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;

/**
 * Which applications of one job to list, and in which order. `$search` is a piece of the applicant's name (any letter case). `$newestFirst` means descending in the sort key: newest
 * applied, or most days in the stage. `$staleOnly` keeps the applications that are stale for a limit of `$staleAfterDays` days. `$hiringManagerVisibleOnly` keeps only the
 * applications a bound hiring manager may see.
 */
final readonly class RecruiterApplicationQuery
{
    public const string SORT_APPLIED_AT = 'applied_at';

    public const string SORT_DAYS_IN_STAGE = 'days_in_stage';

    public function __construct(
        public int $jobId,
        public ?int $stageId = null,
        public ?StageType $stageType = null,
        public ?bool $meetsMustHave = null,
        public ?string $search = null,
        public bool $newestFirst = true,
        public bool $staleOnly = false,
        public int $staleAfterDays = 7,
        public string $sortKey = self::SORT_APPLIED_AT,
        public bool $hiringManagerVisibleOnly = false,
    ) {}

    /** The same query limited, for a hiring manager bound to the stage boundary, to the applications that carry the marker. */
    public function withHiringManagerScope(bool $bound): self
    {
        return new self($this->jobId, $this->stageId, $this->stageType, $this->meetsMustHave, $this->search, $this->newestFirst, $this->staleOnly, $this->staleAfterDays, $this->sortKey, $bound);
    }

    /** The same query for the stale limit of the company. */
    public function withStaleAfterDays(int $days): self
    {
        return new self($this->jobId, $this->stageId, $this->stageType, $this->meetsMustHave, $this->search, $this->newestFirst, $this->staleOnly, $days, $this->sortKey, $this->hiringManagerVisibleOnly);
    }
}
