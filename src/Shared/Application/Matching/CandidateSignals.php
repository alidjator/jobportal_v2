<?php

namespace Src\Shared\Application\Matching;

/**
 * What the matching engine knows about a job seeker: their skills, how long they have worked, their highest education, where they live, what they expect to
 * earn and the words they describe their work with. Built by JobSeeker; it holds no name, contact or protected attribute.
 */
final readonly class CandidateSignals
{
    /**
     * @param  list<int>  $skillIds
     * @param  list<string>  $titles  the job titles of the experience entries and the headline
     */
    public function __construct(
        public int $userId,
        public array $skillIds,
        public int $experienceMonths,
        public bool $freshGraduate,
        public ?int $educationRank,
        public ?int $provinceId,
        public ?int $cityId,
        public ?int $expectedSalary,
        public ?int $expectedSalaryPeriodId,
        public array $titles,
    ) {}
}
