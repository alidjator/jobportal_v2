<?php

namespace Tests\Unit\Recruitment\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\InterviewSlotPlanner;

final class InterviewSlotPlannerTest extends TestCase
{
    private DateTimeZone $jakarta;

    protected function setUp(): void
    {
        $this->jakarta = new DateTimeZone('Asia/Jakarta');
    }

    /** @return list<string> the slots as `Y-m-d H:i` in Jakarta time, start to end */
    private function plan(string $start, int $count, int $duration, int $gap, string $dayStart = '09:00', string $dayEnd = '17:00', array $busy = []): array
    {
        $busy = array_map(fn (array $range): array => [new DateTimeImmutable($range[0], $this->jakarta), new DateTimeImmutable($range[1], $this->jakarta)], $busy);
        $slots = (new InterviewSlotPlanner)->plan(new DateTimeImmutable($start, $this->jakarta), $count, $duration, $gap, $dayStart, $dayEnd, $this->jakarta, $busy);

        return array_map(fn (array $slot): string => $slot[0]->setTimezone($this->jakarta)->format('Y-m-d H:i').'-'.$slot[1]->setTimezone($this->jakarta)->format('H:i'), $slots);
    }

    public function test_slots_follow_one_another_with_the_gap(): void
    {
        $this->assertSame(['2026-10-12 09:00-09:30', '2026-10-12 09:40-10:10', '2026-10-12 10:20-10:50'], $this->plan('2026-10-12 09:00', 3, 30, 10));
    }

    public function test_the_slots_are_in_utc(): void
    {
        $slot = (new InterviewSlotPlanner)->plan(new DateTimeImmutable('2026-10-12 09:00', $this->jakarta), 1, 30, 0, '09:00', '17:00', $this->jakarta)[0];

        $this->assertSame('UTC', $slot[0]->getTimezone()->getName());
        $this->assertSame('2026-10-12 02:00', $slot[0]->format('Y-m-d H:i'));
    }

    public function test_a_slot_that_does_not_fit_moves_to_the_next_working_day(): void
    {
        // Monday 2026-10-12: 15:30-16:30 fits, the next would end at 17:30, so it starts Tuesday at 09:00.
        $this->assertSame(['2026-10-12 15:30-16:30', '2026-10-13 09:00-10:00'], $this->plan('2026-10-12 15:30', 2, 60, 0));
    }

    public function test_friday_rolls_over_to_monday(): void
    {
        $this->assertSame(['2026-10-16 16:00-17:00', '2026-10-19 09:00-10:00'], $this->plan('2026-10-16 16:00', 2, 60, 0));
    }

    public function test_a_start_on_a_weekend_or_outside_the_window_is_moved_into_it(): void
    {
        $this->assertSame(['2026-10-12 09:00-09:30'], $this->plan('2026-10-10 11:00', 1, 30, 0));
        $this->assertSame(['2026-10-12 09:00-09:30'], $this->plan('2026-10-12 06:00', 1, 30, 0));
        $this->assertSame(['2026-10-13 09:00-09:30'], $this->plan('2026-10-12 17:00', 1, 30, 0));
    }

    public function test_a_slot_overlapping_a_busy_range_starts_when_it_ends(): void
    {
        $busy = [['2026-10-12 09:15', '2026-10-12 10:00']];

        $this->assertSame(['2026-10-12 10:00-10:30', '2026-10-12 10:30-11:00'], $this->plan('2026-10-12 09:00', 2, 30, 0, busy: $busy));
    }

    public function test_a_slot_that_ends_where_a_busy_range_starts_is_kept(): void
    {
        $this->assertSame(['2026-10-12 09:00-09:30'], $this->plan('2026-10-12 09:00', 1, 30, 0, busy: [['2026-10-12 09:30', '2026-10-12 10:00']]));
    }

    public function test_a_busy_range_at_the_end_of_the_day_pushes_the_slot_to_the_next_working_day(): void
    {
        $busy = [['2026-10-12 16:00', '2026-10-12 17:00']];

        $this->assertSame(['2026-10-13 09:00-09:30'], $this->plan('2026-10-12 16:00', 1, 30, 0, busy: $busy));
    }

    public function test_a_slot_longer_than_the_working_day_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->plan('2026-10-12 09:00', 1, 9 * 60, 0);
    }

    public function test_no_slots_are_planned_for_no_applicants(): void
    {
        $this->assertSame([], $this->plan('2026-10-12 09:00', 0, 30, 0));
    }
}
