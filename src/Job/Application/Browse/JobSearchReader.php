<?php

namespace Src\Job\Application\Browse;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface JobSearchReader
{
    /** @return Page<JobSearchHit> */
    public function page(JobSearchQuery $query, PageRequest $request): Page;

    /**
     * Those of these jobs that are live under the query's rule (`active`, deadline not passed, company not hidden) and match its criteria, in no set order.
     *
     * @param  list<int>  $jobIds
     * @return list<JobSearchHit>
     */
    public function hitsOf(JobSearchQuery $query, array $jobIds): array;
}
