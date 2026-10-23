<?php

namespace Src\Recruitment\Application;

/**
 * The profile, score, origin and process filters of the applicant lists (`docs/design/phase-8-applicant-search.md` section 3). Every part is optional and parts combine
 * with "and". Experience is in whole years. `$offerExpiring` keeps the applications with an unanswered offer that ends within `Offer::EXPIRING_DAYS` (the rule of the `offers_expiring` task). `$states` are `interview_scheduled`, `offer_pending` and `test_pending`; any of them matches.
 *
 * `$educationRankMin` and `$pendingTestApplicationIds` are not read from the request: `ApplicantFilterResolver` fills them from the education level id and from the
 * Assessment port before the reader runs.
 */
final readonly class ApplicantFilters
{
    public const string INTERVIEW_SCHEDULED = 'interview_scheduled';

    public const string OFFER_PENDING = 'offer_pending';

    public const string TEST_PENDING = 'test_pending';

    public const array STATES = [self::INTERVIEW_SCHEDULED, self::OFFER_PENDING, self::TEST_PENDING];

    /**
     * @param  list<int>  $skillIds  every one must be present
     * @param  list<string>  $states
     * @param  list<int>|null  $pendingTestApplicationIds
     * @param  string|null  $appliedFrom  a Jakarta date, `Y-m-d`
     * @param  string|null  $appliedTo  a Jakarta date, `Y-m-d`, included
     */
    public function __construct(
        public ?int $provinceId = null,
        public ?int $cityId = null,
        public ?int $educationLevelId = null,
        public ?int $experienceMinYears = null,
        public ?int $experienceMaxYears = null,
        public array $skillIds = [],
        public ?int $salaryMax = null,
        public ?int $matchMin = null,
        public ?int $ratingMin = null,
        public ?string $appliedFrom = null,
        public ?string $appliedTo = null,
        public ?bool $invited = null,
        public ?bool $returning = null,
        public array $states = [],
        public ?int $educationRankMin = null,
        public ?array $pendingTestApplicationIds = null,
        public bool $offerExpiring = false,
    ) {}

    /** Whether any filter reads the facets (the applicant's profile as it was when applying). */
    public function usesFacets(): bool
    {
        return $this->provinceId !== null || $this->cityId !== null || $this->educationLevelId !== null || $this->experienceMinYears !== null || $this->experienceMaxYears !== null
            || $this->skillIds !== [] || $this->salaryMax !== null || $this->invited !== null || $this->returning !== null;
    }

    public function wantsTestPending(): bool
    {
        return in_array(self::TEST_PENDING, $this->states, true);
    }

    /**
     * The same filters with the values the request cannot carry.
     *
     * @param  list<int>|null  $pendingTestApplicationIds
     */
    public function resolved(?int $educationRankMin, ?array $pendingTestApplicationIds): self
    {
        return new self(...[...get_object_vars($this), 'educationRankMin' => $educationRankMin, 'pendingTestApplicationIds' => $pendingTestApplicationIds]);
    }
}
