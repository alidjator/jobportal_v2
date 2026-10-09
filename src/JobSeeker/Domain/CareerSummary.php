<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;

/** What the history of a job seeker adds up to: how long they have worked, and whether they are a fresh graduate. */
final class CareerSummary
{
    /** A fresh graduate has worked less than this many months. */
    public const int FRESH_GRADUATE_MONTHS = 12;

    /**
     * The months worked. Each period runs from its start month to its end month, both included; a period with no end (or still current) runs to the month of
     * `$today`. Months that two periods share count once, so working two jobs at the same time does not add up.
     *
     * @param  list<array{start: DateTimeImmutable, end: DateTimeImmutable|null}>  $periods
     */
    public static function monthsWorked(array $periods, DateTimeImmutable $today): int
    {
        $current = self::index($today);
        $ranges = [];

        foreach ($periods as $period) {
            $start = self::index($period['start']);
            $end = $period['end'] === null ? $current : self::index($period['end']);

            if ($end >= $start) {
                $ranges[] = [$start, $end];
            }
        }

        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $months = 0;
        $reached = null;

        foreach ($ranges as [$start, $end]) {
            $from = $reached === null ? $start : max($start, $reached + 1);

            if ($end >= $from) {
                $months += $end - $from + 1;
            }

            $reached = $reached === null ? $end : max($reached, $end);
        }

        return $months;
    }

    /**
     * Less than 12 months worked, and still studying or graduated this year or last year.
     *
     * @param  list<array{end_year: int|null, is_current: bool}>  $educations
     */
    public static function isFreshGraduate(int $monthsWorked, array $educations, int $year): bool
    {
        if ($monthsWorked >= self::FRESH_GRADUATE_MONTHS) {
            return false;
        }

        foreach ($educations as $education) {
            if ($education['is_current'] || ($education['end_year'] !== null && $education['end_year'] >= $year - 1)) {
                return true;
            }
        }

        return false;
    }

    private static function index(DateTimeImmutable $date): int
    {
        return (int) $date->format('Y') * 12 + (int) $date->format('n') - 1;
    }
}
