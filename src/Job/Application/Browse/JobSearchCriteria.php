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

    /**
     * The criteria of a saved search, kept as the names of the search filters: `q` (a string), `work_mode` (a list of work modes), the id filters (`category_id`,
     * `role_id`, `job_type_id`, `job_level_id`, `experience_level_id`, `education_level_id`, `work_time_id`, `province_id`, `city_id`, `skill_id`, `company_id`,
     * each a list of ids) and `salary_min` with `salary_period_id`. A filter that is missing or empty is no filter.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function fromFilters(array $filters, JobSearchSort $sort = JobSearchSort::PublishedAtDescending): self
    {
        $words = [];

        foreach (preg_split('/\s+/u', trim(is_string($filters['q'] ?? null) ? $filters['q'] : ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $words[mb_strtolower($word)] = $word;
        }

        $ids = static fn (string $name): array => array_values(array_unique(array_map('intval', is_array($filters[$name] ?? null) ? $filters[$name] : [])));
        $modes = is_array($filters['work_mode'] ?? null) ? $filters['work_mode'] : [];

        return new self(
            array_values($words),
            $ids('category_id'),
            $ids('role_id'),
            $ids('job_type_id'),
            $ids('job_level_id'),
            $ids('experience_level_id'),
            $ids('education_level_id'),
            $ids('work_time_id'),
            array_values(array_unique(array_map(static fn (mixed $mode): WorkMode => WorkMode::from((string) $mode), $modes), SORT_REGULAR)),
            $ids('province_id'),
            $ids('city_id'),
            $ids('skill_id'),
            $ids('company_id'),
            isset($filters['salary_min']) ? (int) $filters['salary_min'] : null,
            isset($filters['salary_period_id']) ? (int) $filters['salary_period_id'] : null,
            $sort,
        );
    }
}
