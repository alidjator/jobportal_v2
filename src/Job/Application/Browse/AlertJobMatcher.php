<?php

namespace Src\Job\Application\Browse;

use DateTimeImmutable;
use Src\Shared\Application\Job\JobMatcher;
use Src\Shared\Application\Job\JobMatches;
use Src\Shared\Application\Pagination\PageRequest;

/** Answers a saved search through the public search reader, so a job alert and the search always agree on what matches. */
final readonly class AlertJobMatcher implements JobMatcher
{
    public function __construct(private JobSearchReader $reader, private JobSearchQueryBuilder $queries) {}

    public function match(array $filters, ?DateTimeImmutable $publishedAfter, ?DateTimeImmutable $publishedUntil, ?int $onlyJobId, int $limit): JobMatches
    {
        $query = $this->queries->build(JobSearchCriteria::fromFilters($filters), $publishedAfter, $publishedUntil, $onlyJobId);
        $page = $this->reader->page($query, new PageRequest(1, max(1, min($limit, PageRequest::MAX_PER_PAGE))));

        return new JobMatches($page->total, array_map(static fn (JobSearchHit $hit): int => $hit->id, $page->items));
    }
}
