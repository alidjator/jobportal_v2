<?php

namespace Tests\Unit\Recruitment\Domain;

use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\EmploymentType;
use Src\Recruitment\Domain\OfferAllowance;
use Src\Recruitment\Domain\OfferTerms;
use Src\Recruitment\Domain\OfferWorkMode;
use Src\Recruitment\Domain\SalaryBasis;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final class OfferTermsTest extends TestCase
{
    /** @param  list<OfferAllowance>  $allowances */
    private function terms(EmploymentType $type = EmploymentType::Permanent, ?int $contract = null, ?int $probation = null, OfferWorkMode $mode = OfferWorkMode::Onsite, ?string $location = 'Jakarta', array $allowances = [], bool $rules = true): OfferTerms
    {
        return OfferTerms::make($type, $contract, $probation, $mode, $location, SalaryBasis::Gross, $allowances, [], $rules);
    }

    private function code(callable $make): string
    {
        try {
            $make();
        } catch (BusinessRuleViolation $e) {
            return $e->errorCode();
        }

        return 'none';
    }

    public function test_a_permanent_offer_may_have_a_probation_of_up_to_three_months(): void
    {
        $this->assertSame(3, $this->terms(probation: 3)->probationMonths);
        $this->assertNull($this->terms(probation: 0)->probationMonths);
        $this->assertSame('probation-too-long', $this->code(fn () => $this->terms(probation: 4)));
    }

    public function test_a_probation_is_only_for_a_permanent_employee_while_the_labour_rules_are_on(): void
    {
        $this->assertSame('probation-not-allowed', $this->code(fn () => $this->terms(EmploymentType::Contract, 12, 2)));
        $this->assertSame(2, $this->terms(EmploymentType::Contract, 12, 2, rules: false)->probationMonths);
        $this->assertSame(10, $this->terms(probation: 10, rules: false)->probationMonths);
    }

    public function test_the_field_limits_apply_with_the_labour_rules_off(): void
    {
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(probation: 13, rules: false)));
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(EmploymentType::Contract, 61, rules: false)));
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(EmploymentType::Contract, 0, rules: false)));
    }

    public function test_an_internship_lasts_at_most_twelve_months_with_the_labour_rules_on(): void
    {
        $this->assertSame(12, $this->terms(EmploymentType::Internship, 12)->contractMonths);
        $this->assertSame('contract-too-long', $this->code(fn () => $this->terms(EmploymentType::Internship, 13)));
        $this->assertSame(24, $this->terms(EmploymentType::Internship, 24, rules: false)->contractMonths);
    }

    public function test_the_contract_length_belongs_to_a_contract_or_an_internship_only(): void
    {
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(EmploymentType::Contract)));
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(EmploymentType::Permanent, 12)));
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(EmploymentType::Daily, 1)));
        $this->assertSame(60, $this->terms(EmploymentType::Contract, 60)->contractMonths);
    }

    public function test_the_work_location_is_needed_unless_the_work_is_remote(): void
    {
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(location: null)));
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(location: '   ')));
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(mode: OfferWorkMode::Hybrid, location: null)));
        $this->assertNull($this->terms(mode: OfferWorkMode::Remote, location: null)->workLocation);
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(location: str_repeat('a', 256))));
    }

    public function test_allowances_are_limited_in_number_name_and_amount(): void
    {
        $line = new OfferAllowance('Transport', 500_000);

        $this->assertSame(500_000, $this->terms(allowances: [$line])->allowancesTotal());
        $this->assertSame(1_500_000, $this->terms(allowances: [$line, new OfferAllowance('Meal', 1_000_000)])->allowancesTotal());
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(allowances: array_fill(0, 11, $line))));
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(allowances: [new OfferAllowance('', 1000)])));
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(allowances: [new OfferAllowance(str_repeat('a', 101), 1000)])));
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(allowances: [new OfferAllowance('Bonus', 0)])));
        $this->assertSame('invalid-offer', $this->code(fn () => $this->terms(allowances: [new OfferAllowance('Bonus', 1_000_000_001)])));
    }
}
