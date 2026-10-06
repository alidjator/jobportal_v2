<?php

namespace Src\Job\Application;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface JobReader
{
    /** @return Page<JobListItem> */
    public function page(JobListQuery $query, PageRequest $request): Page;
}
