<?php

namespace Src\Recruitment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The terms of an offer beyond the position, salary and start date: employment type, contract length and probation, work mode and location, salary basis,
 * allowances and benefits. The benefits are a snapshot of their names (Indonesian and English) as they were when the offer was sent.
 *
 * The field limits always apply (422 `invalid-offer`); the labour-law checks (PP 35/2021: probation only for a permanent employee and at most 3 months, a contract
 * at most 60 months, an internship at most 12) apply while the setting `recruitment.offer_labor_rules` is on.
 */
final readonly class OfferTerms
{
    public const int WORK_LOCATION_MAX = 255;

    public const int ALLOWANCES_MAX = 10;

    public const int CONTRACT_MONTHS_MAX = 60;

    public const int INTERNSHIP_MONTHS_MAX = 12;

    public const int PROBATION_MONTHS_MAX = 12;

    public const int PROBATION_MONTHS_LEGAL_MAX = 3;

    /**
     * @param  list<OfferAllowance>  $allowances
     * @param  list<array{id: int, name: array{id: string, en: string}}>  $benefits
     */
    public function __construct(
        public EmploymentType $employmentType,
        public ?int $contractMonths,
        public ?int $probationMonths,
        public OfferWorkMode $workMode,
        public ?string $workLocation,
        public SalaryBasis $salaryBasis,
        public array $allowances,
        public array $benefits,
    ) {}

    /**
     * @param  list<OfferAllowance>  $allowances
     * @param  list<array{id: int, name: array{id: string, en: string}}>  $benefits
     *
     * @throws BusinessRuleViolation `invalid-offer`, or with the labour-law checks on `probation-not-allowed`, `probation-too-long` or `contract-too-long`
     */
    public static function make(
        EmploymentType $employmentType,
        ?int $contractMonths,
        ?int $probationMonths,
        OfferWorkMode $workMode,
        ?string $workLocation,
        SalaryBasis $salaryBasis,
        array $allowances,
        array $benefits,
        bool $laborRules,
    ): self {
        $workLocation = $workLocation === null ? null : trim($workLocation);
        $workLocation = $workLocation === '' ? null : $workLocation;
        $probationMonths = $probationMonths === 0 ? null : $probationMonths;

        self::assertFields($employmentType, $contractMonths, $probationMonths, $workMode, $workLocation, $allowances);

        if ($laborRules) {
            self::assertLaborRules($employmentType, $contractMonths, $probationMonths);
        }

        return new self($employmentType, $contractMonths, $probationMonths, $workMode, $workLocation, $salaryBasis, $allowances, $benefits);
    }

    public function allowancesTotal(): int
    {
        return array_sum(array_map(static fn (OfferAllowance $allowance): int => $allowance->amount, $this->allowances));
    }

    /**
     * @param  list<OfferAllowance>  $allowances
     *
     * @throws BusinessRuleViolation `invalid-offer`
     */
    private static function assertFields(EmploymentType $type, ?int $contractMonths, ?int $probationMonths, OfferWorkMode $workMode, ?string $workLocation, array $allowances): void
    {
        $errors = match (true) {
            $type->hasContractLength() && $contractMonths === null => ['contract_months' => 'Give the length of the contract in months.'],
            ! $type->hasContractLength() && $contractMonths !== null => ['contract_months' => 'Only a contract or an internship has a length in months.'],
            $contractMonths !== null && ($contractMonths < 1 || $contractMonths > self::CONTRACT_MONTHS_MAX) => ['contract_months' => 'The length must be between 1 and '.self::CONTRACT_MONTHS_MAX.' months.'],
            $probationMonths !== null && ($probationMonths < 0 || $probationMonths > self::PROBATION_MONTHS_MAX) => ['probation_months' => 'The probation must be between 0 and '.self::PROBATION_MONTHS_MAX.' months.'],
            $workMode !== OfferWorkMode::Remote && $workLocation === null => ['work_location' => 'Give the work location.'],
            $workLocation !== null && mb_strlen($workLocation) > self::WORK_LOCATION_MAX => ['work_location' => 'The work location can have at most '.self::WORK_LOCATION_MAX.' characters.'],
            count($allowances) > self::ALLOWANCES_MAX => ['allowances' => 'An offer can have at most '.self::ALLOWANCES_MAX.' allowances.'],
            default => self::allowanceError($allowances),
        };

        if ($errors !== []) {
            $message = (string) reset($errors);

            throw new BusinessRuleViolation($message, 'invalid-offer', extensions: ['errors' => array_map(static fn (string $text): array => [$text], $errors)]);
        }
    }

    /**
     * @param  list<OfferAllowance>  $allowances
     * @return array<string, string>
     */
    private static function allowanceError(array $allowances): array
    {
        foreach ($allowances as $index => $allowance) {
            $name = trim($allowance->name);

            if ($name === '' || mb_strlen($name) > OfferAllowance::NAME_MAX) {
                return ["allowances.{$index}.name" => 'An allowance needs a name of at most '.OfferAllowance::NAME_MAX.' characters.'];
            }

            if ($allowance->amount < 1 || $allowance->amount > OfferAllowance::AMOUNT_MAX) {
                return ["allowances.{$index}.amount" => 'An allowance must be between 1 and '.OfferAllowance::AMOUNT_MAX.' Rupiah per month.'];
            }
        }

        return [];
    }

    /** @throws BusinessRuleViolation `probation-not-allowed`, `probation-too-long` or `contract-too-long` */
    private static function assertLaborRules(EmploymentType $type, ?int $contractMonths, ?int $probationMonths): void
    {
        if ($probationMonths !== null && $type !== EmploymentType::Permanent) {
            throw new BusinessRuleViolation('A probation period is only allowed for a permanent employee.', 'probation-not-allowed', extensions: ['errors' => ['probation_months' => ['A probation period is only allowed for a permanent employee.']]]);
        }

        if ($probationMonths !== null && $probationMonths > self::PROBATION_MONTHS_LEGAL_MAX) {
            throw new BusinessRuleViolation('A probation period can be at most '.self::PROBATION_MONTHS_LEGAL_MAX.' months.', 'probation-too-long', extensions: ['errors' => ['probation_months' => ['A probation period can be at most '.self::PROBATION_MONTHS_LEGAL_MAX.' months.']]]);
        }

        if ($type === EmploymentType::Internship && $contractMonths !== null && $contractMonths > self::INTERNSHIP_MONTHS_MAX) {
            throw new BusinessRuleViolation('An internship can last at most '.self::INTERNSHIP_MONTHS_MAX.' months.', 'contract-too-long', extensions: ['errors' => ['contract_months' => ['An internship can last at most '.self::INTERNSHIP_MONTHS_MAX.' months.']]]);
        }
    }
}
