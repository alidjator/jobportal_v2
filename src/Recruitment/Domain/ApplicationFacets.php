<?php

namespace Src\Recruitment\Domain;

/**
 * What the applicant lists search and filter on, copied from the profile snapshot (`docs/design/phase-8-applicant-search.md` section 1). It holds no contact data and no
 * protected attribute. `expectedSalaryMonthly` is set only for a salary given per month.
 */
final readonly class ApplicationFacets
{
    /** @param  list<int>  $skillIds */
    public function __construct(
        public ?int $provinceId,
        public ?int $cityId,
        public ?int $educationRank,
        public int $experienceMonths,
        public ?int $expectedSalaryMonthly,
        public array $skillIds,
        public string $searchText,
        public bool $fromInvitation,
        public bool $returning,
    ) {}
}
