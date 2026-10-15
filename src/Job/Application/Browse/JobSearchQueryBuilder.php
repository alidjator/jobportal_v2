<?php

namespace Src\Job\Application\Browse;

use DateTimeImmutable;
use Src\Job\Application\JakartaDate;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\PublicCompanies;

/**
 * Turns criteria into what the reader runs: the keyword is kept as plain text, today is the date in Jakarta, the posting-date window gets its start and the
 * hidden companies are excluded. The one place that does it, so the public search and the job alerts never disagree on what matches.
 */
final readonly class JobSearchQueryBuilder
{
    public function __construct(
        private PublicCompanies $publicCompanies,
        private Clock $clock,
    ) {}

    public function build(JobSearchCriteria $criteria, ?DateTimeImmutable $publishedAfter = null, ?DateTimeImmutable $publishedUntil = null, ?int $onlyJobId = null): JobSearchQuery
    {
        $now = $this->clock->now();
        // An alert about one job (the immediate alerts) ignores the window: the job just went live.
        $postedSince = $criteria->postedWithin !== null && $onlyJobId === null ? $now->modify("-{$criteria->postedWithin} days") : null;

        return new JobSearchQuery($criteria, $this->keyword($criteria), JakartaDate::today($now), $this->publicCompanies->hiddenIds(), $publishedAfter, $publishedUntil, $onlyJobId, $postedSince);
    }

    /** The words of the keyword as one text, or null when there is nothing to search for; the database splits it into words the way it indexes them. */
    private function keyword(JobSearchCriteria $criteria): ?string
    {
        $keyword = implode(' ', $criteria->words);

        return preg_match('/[\p{L}\p{N}]/u', $keyword) === 1 ? $keyword : null;
    }
}
