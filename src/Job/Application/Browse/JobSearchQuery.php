<?php

namespace Src\Job\Application\Browse;

use DateTimeImmutable;

/**
 * What the reader runs: the visitor's criteria with the keyword resolved to words, plus the rule of what is searchable at all: only `active` jobs whose
 * deadline is not before `$today` (Jakarta time) and whose company is not one of `$excludedCompanyIds` (suspended or hidden companies). A job alert narrows it
 * further: only jobs first published after `$publishedAfter` and up to `$publishedUntil`, and only the job `$onlyJobId`.
 */
final readonly class JobSearchQuery
{
    /**
     * @param  list<SearchWord>  $words
     * @param  list<int>  $excludedCompanyIds
     */
    public function __construct(
        public JobSearchCriteria $criteria,
        public array $words,
        public DateTimeImmutable $today,
        public array $excludedCompanyIds,
        public ?DateTimeImmutable $publishedAfter = null,
        public ?DateTimeImmutable $publishedUntil = null,
        public ?int $onlyJobId = null,
    ) {}
}
