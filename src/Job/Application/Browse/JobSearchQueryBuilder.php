<?php

namespace Src\Job\Application\Browse;

use DateTimeImmutable;
use Src\Job\Application\JakartaDate;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\References\TagRegistry;

/**
 * Turns criteria into what the reader runs: every keyword word is looked up (the tags and the companies it matches), today is the date in Jakarta and the
 * hidden companies are excluded. The one place that does it, so the public search and the job alerts never disagree on what matches.
 */
final readonly class JobSearchQueryBuilder
{
    private const int MAX_MATCHES_PER_WORD = 200;

    public function __construct(
        private PublicCompanies $publicCompanies,
        private CompanyDirectory $companies,
        private TagRegistry $tags,
        private Clock $clock,
    ) {}

    public function build(JobSearchCriteria $criteria, ?DateTimeImmutable $publishedAfter = null, ?DateTimeImmutable $publishedUntil = null, ?int $onlyJobId = null): JobSearchQuery
    {
        $words = array_map(
            fn (string $word): SearchWord => new SearchWord(
                $word,
                $this->tags->idsMatching($word, self::MAX_MATCHES_PER_WORD),
                $this->companies->idsByName($word, self::MAX_MATCHES_PER_WORD),
            ),
            $criteria->words,
        );

        return new JobSearchQuery($criteria, $words, JakartaDate::today($this->clock->now()), $this->publicCompanies->hiddenIds(), $publishedAfter, $publishedUntil, $onlyJobId);
    }
}
