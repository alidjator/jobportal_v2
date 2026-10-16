<?php

namespace Src\Job\Application;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface JobReader
{
    /** @return Page<JobListItem> */
    public function page(JobListQuery $query, PageRequest $request): Page;

    /**
     * Every job of the company assigned to the account, whatever its status, newest first. Not paginated: one member holds few jobs.
     *
     * @return list<JobListItem>
     */
    public function assignedTo(int $companyId, int $userId): array;

    /**
     * The explicit stage boundary of the jobs of the company that have one (the other jobs use the default).
     *
     * @return array<int, int> the stage id by job id
     */
    public function hiringManagerBoundaries(int $companyId): array;
}
