<?php

namespace Src\Job\Application\Browse;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface JobSearchReader
{
    /** @return Page<JobSearchHit> */
    public function page(JobSearchQuery $query, PageRequest $request): Page;

    /**
     * The first `$limit` live jobs that match the query, by relevance when it has a keyword and newest first otherwise, whatever sort the criteria name.
     *
     * @return list<JobSearchHit>
     */
    public function topHits(JobSearchQuery $query, int $limit): array;

    /**
     * Up to `$limit` live jobs that share the role (or, when the subject has no role, the category) of the subject, other than the subject: the same role first,
     * then the same city, the same province, a level within one step, and the newest. At most `$perCompany` of one company. The query only carries the rule of what is live.
     *
     * @return list<JobSearchHit>
     */
    public function similarTo(JobSearchQuery $query, SimilarJobsQuery $subject, int $limit, int $perCompany): array;

    /**
     * Those of these jobs that are live under the query's rule (`active`, deadline not passed, company not hidden) and match its criteria, in no set order.
     *
     * @param  list<int>  $jobIds
     * @return list<JobSearchHit>
     */
    public function hitsOf(JobSearchQuery $query, array $jobIds): array;
}
