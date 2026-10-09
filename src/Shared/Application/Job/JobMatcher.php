<?php

namespace Src\Shared\Application\Job;

use DateTimeImmutable;

/**
 * Asks which live jobs a saved search matches, by the same rule as the public job search. Provided by Job. `$filters` are the names of the search filters
 * (`q`, `role_id`, `salary_min` with `salary_period_id`, ...), as `JobSearchCriteria::fromFilters` reads them.
 */
interface JobMatcher
{
    /**
     * The live jobs that match, newest first. `$publishedAfter` and `$publishedUntil` bound the time the job first went live (after the first, up to and including
     * the second); `$onlyJobId` asks about that one job. At most `$limit` ids come back; the total is the whole count.
     *
     * @param  array<string, mixed>  $filters
     */
    public function match(array $filters, ?DateTimeImmutable $publishedAfter, ?DateTimeImmutable $publishedUntil, ?int $onlyJobId, int $limit): JobMatches;
}
