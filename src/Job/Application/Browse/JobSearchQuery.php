<?php

namespace Src\Job\Application\Browse;

use DateTimeImmutable;

/**
 * What the reader runs: the visitor's criteria with the keyword as plain text (null when it has no letter or digit), plus the rule of what is searchable at all: only `active` jobs whose
 * deadline is not before `$today` (Jakarta time) and whose company is not one of `$excludedCompanyIds` (suspended or hidden companies). A job alert narrows it
 * further: only jobs first published after `$publishedAfter` and up to `$publishedUntil`, and only the job `$onlyJobId`. `$postedSince` is the start of the posting-date window.
 */
final readonly class JobSearchQuery
{
    /**
     * @param  list<int>  $excludedCompanyIds
     */
    public function __construct(
        public JobSearchCriteria $criteria,
        public ?string $keyword,
        public DateTimeImmutable $today,
        public array $excludedCompanyIds,
        public ?DateTimeImmutable $publishedAfter = null,
        public ?DateTimeImmutable $publishedUntil = null,
        public ?int $onlyJobId = null,
        public ?DateTimeImmutable $postedSince = null,
    ) {}
}
