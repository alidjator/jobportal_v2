<?php

namespace Src\Shared\Application\Job;

use DateTimeImmutable;

/**
 * What a live job offers, as the facts a saved search is compared with (the reverse of `JobMatcher`: one job against many searches). `$ids` is keyed by the
 * name of the search filter (`role_id`, `city_id`, ...); an id the job does not have is null. `$searchVector` is the full text vector of the job (the text form of a `tsvector`: title, tags, company name and
 * description), which a keyword is matched against; empty when the job has none.
 */
final readonly class LiveJobProfile
{
    /**
     * @param  array<string, int|null>  $ids
     * @param  list<int>  $skillIds
     */
    public function __construct(
        public int $jobId,
        public DateTimeImmutable $publishedAt,
        public array $ids,
        public array $skillIds,
        public ?string $workMode,
        public ?int $salaryPeriodId,
        public ?int $salaryCeiling,
        public string $searchVector,
    ) {}
}
