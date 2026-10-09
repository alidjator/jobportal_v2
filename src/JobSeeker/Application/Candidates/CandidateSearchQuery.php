<?php

namespace Src\JobSeeker\Application\Candidates;

use Src\JobSeeker\Domain\Availability;
use Src\JobSeeker\Domain\Gender;

/**
 * What a company asks for in the candidate search. Every field is optional; the lists of skills and languages ask for candidates who have all of them, the other
 * lists for candidates who match any one of them. The minimum education level matches a candidate whose highest education is at that rank or above.
 */
final readonly class CandidateSearchQuery
{
    /**
     * @param  list<int>  $skillIds
     * @param  list<int>  $languageIds
     * @param  list<int>  $experienceLevelIds
     * @param  list<Availability>  $availabilities
     * @param  list<int>  $religionIds
     * @param  list<int>  $nationalityIds
     */
    public function __construct(
        public ?string $keyword = null,
        public array $skillIds = [],
        public array $languageIds = [],
        public ?int $provinceId = null,
        public ?int $cityId = null,
        public array $experienceLevelIds = [],
        public ?int $minEducationLevelId = null,
        public array $availabilities = [],
        public ?int $maxExpectedSalary = null,
        public ?int $salaryPeriodId = null,
        public array $religionIds = [],
        public array $nationalityIds = [],
        public ?int $minAge = null,
        public ?int $maxAge = null,
        public ?Gender $gender = null,
    ) {}

    /** Whether the search filters on religion, nationality, age or gender, which an admin setting can switch off. */
    public function usesSensitiveFilter(): bool
    {
        return $this->religionIds !== [] || $this->nationalityIds !== [] || $this->minAge !== null || $this->maxAge !== null || $this->gender !== null;
    }
}
