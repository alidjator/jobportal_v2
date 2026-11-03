<?php

namespace Tests\Unit\Assessment\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\Assessment\Domain\ReviewTarget;

final class ReviewTargetTest extends TestCase
{
    private function utc(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }

    public function test_the_due_day_counts_working_days_after_the_day_of_the_submission(): void
    {
        // 2026-11-02 is a Monday.
        $this->assertSame('2026-11-04', ReviewTarget::dueOn($this->utc('2026-11-02 03:00'), 2)->format('Y-m-d'));
        $this->assertSame('2026-11-03', ReviewTarget::dueOn($this->utc('2026-11-02 03:00'), 1)->format('Y-m-d'));
    }

    public function test_the_weekend_does_not_count(): void
    {
        // Thursday + 2 working days is Monday; Friday + 1 is Monday; Saturday and Sunday count from Monday.
        $this->assertSame('2026-11-09', ReviewTarget::dueOn($this->utc('2026-11-05 03:00'), 2)->format('Y-m-d'));
        $this->assertSame('2026-11-09', ReviewTarget::dueOn($this->utc('2026-11-06 03:00'), 1)->format('Y-m-d'));
        $this->assertSame('2026-11-09', ReviewTarget::dueOn($this->utc('2026-11-07 03:00'), 1)->format('Y-m-d'));
        $this->assertSame('2026-11-10', ReviewTarget::dueOn($this->utc('2026-11-08 03:00'), 2)->format('Y-m-d'));
        $this->assertSame('2026-11-16', ReviewTarget::dueOn($this->utc('2026-11-02 03:00'), 10)->format('Y-m-d'));
    }

    public function test_the_day_is_the_jakarta_day_not_the_utc_day(): void
    {
        // 20:00 UTC on Friday is 03:00 on Saturday in Jakarta, so one working day later is Monday.
        $this->assertSame('2026-11-09', ReviewTarget::dueOn($this->utc('2026-11-06 20:00'), 1)->format('Y-m-d'));
        $this->assertSame('2026-11-07', ReviewTarget::today($this->utc('2026-11-06 20:00')));
    }

    public function test_a_report_is_overdue_only_after_its_due_day(): void
    {
        $due = $this->utc('2026-11-04 00:00');

        $this->assertFalse(ReviewTarget::isOverdue($due, $this->utc('2026-11-04 16:00')));
        $this->assertTrue(ReviewTarget::isOverdue($due, $this->utc('2026-11-04 17:00')));
        $this->assertTrue(ReviewTarget::isOverdue($due, $this->utc('2026-11-10 00:00')));
        $this->assertFalse(ReviewTarget::isOverdue(null, $this->utc('2026-11-10 00:00')));
    }

    public function test_the_target_stays_between_one_and_ten_days(): void
    {
        ReviewTarget::assertValid(1);
        ReviewTarget::assertValid(10);

        $this->expectException(InvalidArgumentException::class);
        ReviewTarget::assertValid(11);
    }
}
