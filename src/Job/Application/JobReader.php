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
}
