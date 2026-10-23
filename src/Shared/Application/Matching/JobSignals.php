<?php

namespace Src\Shared\Application\Matching;

/**
 * What the matching engine knows about a job. Built by Job. `minYears` and `maxYears` are the range of the job's experience level (null without a level);
 * `freshGraduateLevel` marks the "Fresh Graduated" level, which the engine tells apart from "Less than a Year" (they share the range 0 to 1 year).
 * The salary is the upper bound the company shows, and null when it is hidden or empty. `provinceId` and `cityId` are the job's main place; `places` holds the
 * province and city of every company location of the job (the main one included), empty for a job with an address of its own.
 */
final readonly class JobSignals
{
    /**
     * @param  list<int>  $skillIds
     * @param  list<array{province_id: ?int, city_id: ?int}>  $places
     */
    public function __construct(
        public int $jobId,
        public string $title,
        public ?string $roleName,
        public array $skillIds,
        public ?int $minYears,
        public ?int $maxYears,
        public bool $freshGraduateLevel,
        public ?int $minEducationRank,
        public bool $remote,
        public ?int $provinceId,
        public ?int $cityId,
        public ?int $salaryMax,
        public ?int $salaryPeriodId,
        public array $places = [],
    ) {}
}
