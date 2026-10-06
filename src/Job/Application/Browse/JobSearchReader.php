<?php

namespace Src\Job\Application\Browse;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface JobSearchReader
{
    /** @return Page<JobSearchHit> */
    public function page(JobSearchQuery $query, PageRequest $request): Page;
}
