<?php

namespace Tests\Unit\JobSeeker\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\JobSeeker\Domain\CareerSummary;

final class CareerSummaryTest extends TestCase
{
    private function month(string $month): DateTimeImmutable
    {
        return new DateTimeImmutable($month.'-01');
    }

    public function test_a_period_counts_its_first_and_last_month(): void
    {
        $this->assertSame(12, CareerSummary::monthsWorked([['start' => $this->month('2023-01'), 'end' => $this->month('2023-12')]], $this->month('2026-10')));
        $this->assertSame(28, CareerSummary::monthsWorked([['start' => $this->month('2022-03'), 'end' => $this->month('2024-06')]], $this->month('2026-10')));
        $this->assertSame(1, CareerSummary::monthsWorked([['start' => $this->month('2023-05'), 'end' => $this->month('2023-05')]], $this->month('2026-10')));
    }

    public function test_an_open_period_runs_to_the_current_month(): void
    {
        $this->assertSame(10, CareerSummary::monthsWorked([['start' => $this->month('2026-01'), 'end' => null]], $this->month('2026-10')));
    }

    public function test_months_shared_by_two_periods_count_once(): void
    {
        $periods = [
            ['start' => $this->month('2022-01'), 'end' => $this->month('2022-12')],
            ['start' => $this->month('2022-07'), 'end' => $this->month('2023-06')],
            ['start' => $this->month('2022-03'), 'end' => $this->month('2022-04')],
            ['start' => $this->month('2024-01'), 'end' => $this->month('2024-02')],
        ];

        // January 2022 to June 2023 is 18 months, plus 2 more in 2024.
        $this->assertSame(20, CareerSummary::monthsWorked($periods, $this->month('2026-10')));
    }

    public function test_no_periods_is_no_months(): void
    {
        $this->assertSame(0, CareerSummary::monthsWorked([], $this->month('2026-10')));
    }

    public function test_a_fresh_graduate_has_worked_under_a_year_and_is_studying_or_graduated_this_or_last_year(): void
    {
        $this->assertTrue(CareerSummary::isFreshGraduate(0, [['end_year' => null, 'is_current' => true]], 2026));
        $this->assertTrue(CareerSummary::isFreshGraduate(6, [['end_year' => 2026, 'is_current' => false]], 2026));
        $this->assertTrue(CareerSummary::isFreshGraduate(11, [['end_year' => 2025, 'is_current' => false]], 2026));
        $this->assertFalse(CareerSummary::isFreshGraduate(0, [['end_year' => 2024, 'is_current' => false]], 2026));
        $this->assertFalse(CareerSummary::isFreshGraduate(12, [['end_year' => 2026, 'is_current' => false]], 2026));
        $this->assertFalse(CareerSummary::isFreshGraduate(0, [], 2026));
        $this->assertFalse(CareerSummary::isFreshGraduate(0, [['end_year' => null, 'is_current' => false]], 2026));
    }
}
