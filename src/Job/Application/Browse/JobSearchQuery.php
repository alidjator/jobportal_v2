<?php

namespace Src\Job\Application\Browse;

use DateTimeImmutable;

/**
 * What the reader runs: the visitor's criteria with the keyword resolved to words, plus the rule of what is searchable at all: only `active` jobs whose
 * deadline is not before `$today` (Jakarta time) and whose company is not one of `$excludedCompanyIds` (suspended or hidden companies).
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
    ) {}
}
