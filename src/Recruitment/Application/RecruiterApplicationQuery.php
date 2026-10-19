<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;

/**
 * Which applications to list, and in which order. The scope is one job (`$jobId`), or the company's applications across jobs (`$jobId` null, `$companyId` set): then `$jobIds`
 * limits them to those jobs (null: every job of the company) and `$activeOnly` leaves out the hired, rejected and withdrawn ones unless a stage filter asks for them.
 * `$search` is a piece of the applicant's name or of the words of the profile (any letter case, accents ignored). `$newestFirst` means descending in the sort key: newest
 * applied, highest score or rating, or most days in the stage. `$staleOnly` keeps the applications that are stale for a limit of `$staleAfterDays` days.
 * `$hiringManagerBoundJobIds` are the jobs where the member is a bound hiring manager: there only the applications that carry the marker are listed. `$interviewState`
 * keeps the applications in an interview stage in that state (`not_scheduled`, `pending`, `confirmed`, `completed`). `$filters` are the profile, score, origin and process
 * filters.
 */
final readonly class RecruiterApplicationQuery
{
    public const string SORT_APPLIED_AT = 'applied_at';

    public const string SORT_DAYS_IN_STAGE = 'days_in_stage';

    public const string SORT_MATCH_SCORE = 'match_score';

    public const string SORT_RATING = 'rating';

    /**
     * @param  list<int>  $hiringManagerBoundJobIds
     * @param  list<int>|null  $jobIds
     */
    public function __construct(
        public ?int $jobId,
        public ?int $stageId = null,
        public ?StageType $stageType = null,
        public ?bool $meetsMustHave = null,
        public ?string $search = null,
        public bool $newestFirst = true,
        public bool $staleOnly = false,
        public int $staleAfterDays = 7,
        public string $sortKey = self::SORT_APPLIED_AT,
        public array $hiringManagerBoundJobIds = [],
        public ?string $interviewState = null,
        public ApplicantFilters $filters = new ApplicantFilters,
        public ?int $companyId = null,
        public ?array $jobIds = null,
        public bool $activeOnly = false,
    ) {}

    /** The same query limited, for a hiring manager bound to the stage boundary of this job, to the applications that carry the marker. */
    public function withHiringManagerScope(bool $bound): self
    {
        return $this->withBoundJobs($bound && $this->jobId !== null ? [$this->jobId] : []);
    }

    /**
     * The same query with the jobs where the member is a bound hiring manager.
     *
     * @param  list<int>  $jobIds
     */
    public function withBoundJobs(array $jobIds): self
    {
        return new self(...[...get_object_vars($this), 'hiringManagerBoundJobIds' => $jobIds]);
    }

    /** The same query for the stale limit of the company. */
    public function withStaleAfterDays(int $days): self
    {
        return new self(...[...get_object_vars($this), 'staleAfterDays' => $days]);
    }

    /** The same query without the stage filters and the "active only" default: what the pipeline columns count. */
    public function withoutStageFilters(): self
    {
        return new self(...[...get_object_vars($this), 'stageId' => null, 'stageType' => null, 'activeOnly' => false]);
    }

    /** The same query with these filters. */
    public function withFilters(ApplicantFilters $filters): self
    {
        return new self(...[...get_object_vars($this), 'filters' => $filters]);
    }

    /**
     * The same query for the company's applications across the jobs the member may see (null: all of them).
     *
     * @param  list<int>|null  $jobIds
     */
    public function acrossJobs(int $companyId, ?array $jobIds): self
    {
        return new self(...[...get_object_vars($this), 'jobId' => null, 'companyId' => $companyId, 'jobIds' => $jobIds, 'activeOnly' => true]);
    }
}
