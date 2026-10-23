<?php

namespace Src\Job\Application;

use Src\Job\Domain\WorkMode;

/**
 * The extra filters of the company's job list (`docs/design/phase-8-job-posting.md` section 4). The id lists match any of their values; a place filter matches a job through any
 * of its work locations. `publishedFrom` and `publishedTo` are Jakarta dates (the last day counts), `deadlineFrom` is a day, `createdBy` and `assignee` are member ids and `filled`
 * keeps the jobs whose hires reached their vacancies (true) or not (false).
 */
final readonly class JobListFilters
{
    /**
     * @param  list<int>  $provinceIds
     * @param  list<int>  $cityIds
     * @param  list<WorkMode>  $workModes
     * @param  list<int>  $jobTypeIds
     * @param  list<int>  $categoryIds
     */
    public function __construct(
        public array $provinceIds = [],
        public array $cityIds = [],
        public array $workModes = [],
        public array $jobTypeIds = [],
        public array $categoryIds = [],
        public ?string $publishedFrom = null,
        public ?string $publishedTo = null,
        public ?string $deadlineFrom = null,
        public ?int $createdBy = null,
        public ?int $assignee = null,
        public ?bool $filled = null,
    ) {}
}
