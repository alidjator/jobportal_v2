<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\EmploymentType;
use Src\Recruitment\Domain\OfferAllowance;
use Src\Recruitment\Domain\OfferWorkMode;
use Src\Recruitment\Domain\SalaryBasis;

/** What a recruiter enters as the terms of an offer; the benefits are ids that the offer turns into a snapshot of their names. */
final readonly class OfferTermsInput
{
    /**
     * @param  list<OfferAllowance>  $allowances
     * @param  list<int>  $benefitIds
     */
    public function __construct(
        public EmploymentType $employmentType,
        public ?int $contractMonths,
        public ?int $probationMonths,
        public OfferWorkMode $workMode,
        public ?string $workLocation,
        public SalaryBasis $salaryBasis,
        public array $allowances,
        public array $benefitIds,
    ) {}
}
