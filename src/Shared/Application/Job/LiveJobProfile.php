<?php

namespace Src\Shared\Application\Job;

use DateTimeImmutable;

/**
 * What a live job offers, as the facts a saved search is compared with (the reverse of `JobMatcher`: one job against many searches). `$ids` is keyed by the
 * name of the search filter (`role_id`, `city_id`, ...); an id the job does not have is null. `$searchableTexts` are the texts a keyword may match: the title,
 * the name of the company and the name of every tag.
 */
final readonly class LiveJobProfile
{
    /**
     * @param  array<string, int|null>  $ids
     * @param  list<int>  $skillIds
     * @param  list<string>  $searchableTexts
     */
    public function __construct(
        public int $jobId,
        public DateTimeImmutable $publishedAt,
        public array $ids,
        public array $skillIds,
        public ?string $workMode,
        public ?int $salaryPeriodId,
        public ?int $salaryCeiling,
        public array $searchableTexts,
    ) {}
}
