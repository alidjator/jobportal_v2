<?php

namespace Src\Job\Application\Browse;

use Src\Job\Domain\WorkMode;

/**
 * What a visitor asks of the job search. Every id list matches any of its values (an empty list is no filter). `words` are the keyword split into words: each
 * word must match the title, a tag or the company name. A minimum salary needs its period.
 */
final readonly class JobSearchCriteria
{
    /**
     * @param  list<string>  $words
     * @param  list<int>  $categoryIds
     * @param  list<int>  $roleIds
     * @param  list<int>  $jobTypeIds
     * @param  list<int>  $jobLevelIds
     * @param  list<int>  $experienceLevelIds
     * @param  list<int>  $educationLevelIds
     * @param  list<int>  $workTimeIds
     * @param  list<WorkMode>  $workModes
     * @param  list<int>  $provinceIds
     * @param  list<int>  $cityIds
     * @param  list<int>  $skillIds
     * @param  list<int>  $companyIds
     */
    public function __construct(
        public array $words = [],
        public array $categoryIds = [],
        public array $roleIds = [],
        public array $jobTypeIds = [],
        public array $jobLevelIds = [],
        public array $experienceLevelIds = [],
        public array $educationLevelIds = [],
        public array $workTimeIds = [],
        public array $workModes = [],
        public array $provinceIds = [],
        public array $cityIds = [],
        public array $skillIds = [],
        public array $companyIds = [],
        public ?int $salaryMin = null,
        public ?int $salaryPeriodId = null,
        public JobSearchSort $sort = JobSearchSort::PublishedAtDescending,
    ) {}
}
