<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

/**
 * The months worked, by the rule of `docs/design/phase-3-matching.md`: each entry runs from its start month to its end month, both included; an entry with no end (or
 * still current) runs to the month of `$today`; months that two entries share count once, so two jobs at the same time do not add up.
 */
final class ExperienceMonths
{
    /**
     * @param  list<array{start: string, end: string|null, is_current?: bool}>  $entries  months as `Y-m`, like the experiences of a profile snapshot
     */
    public static function of(array $entries, DateTimeImmutable $today): int
    {
        $current = self::index($today->format('Y-m'));
        $ranges = [];

        foreach ($entries as $entry) {
            $start = self::index($entry['start']);
            $end = ($entry['is_current'] ?? false) || $entry['end'] === null ? $current : self::index($entry['end']);

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

    private static function index(string $month): int
    {
        [$year, $number] = array_map('intval', explode('-', $month));

        return $year * 12 + $number;
    }
}
