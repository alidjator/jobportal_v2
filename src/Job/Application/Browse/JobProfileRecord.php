<?php

namespace Src\Job\Application\Browse;

use DateTimeImmutable;

/**
 * The columns of a job that a saved search can filter on, plus its full text vector.
 */
final readonly class JobProfileRecord
{
    /**
     * @param  array<string, int|list<int>|null>  $ids  keyed by the name of the search filter; the province and the city are lists for a job with several company locations
     * @param  list<int>  $skillIds
     * @param  list<int>  $tagIds
     */
    public function __construct(
        public int $jobId,
        public string $title,
        public int $companyId,
        public ?DateTimeImmutable $publishedAt,
        public array $ids,
        public array $skillIds,
        public array $tagIds,
        public ?string $workMode,
        public bool $salaryVisible,
        public ?int $salaryPeriodId,
        public ?int $salaryCeiling,
        public string $searchVector = '',
    ) {}
}
