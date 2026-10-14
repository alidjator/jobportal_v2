<?php

namespace Src\Recruitment\Infrastructure\Analytics;

/** The arithmetic of the reports: averages in days and ratios, null when there is nothing to divide by. */
final class Figures
{
    /** The average of a sum of seconds over a count, in days with two decimals. */
    public static function days(int $seconds, int $count): ?float
    {
        return $count === 0 ? null : round($seconds / $count / 86400, 2);
    }

    /** A part of a whole as a ratio from 0 to 1 with four decimals, capped at 1 (a period rate can count a move whose start lies before the range). */
    public static function ratio(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round(min(1, $part / $whole), 4);
    }

    /**
     * The average of some values with one decimal; null when there are none.
     *
     * @param  list<float>  $values
     */
    public static function mean(array $values): ?float
    {
        return $values === [] ? null : round(array_sum($values) / count($values), 1);
    }

    /**
     * The median of some values with one decimal; null when fewer than the smallest group the report may show.
     *
     * @param  list<float>  $values
     */
    public static function median(array $values, int $minGroup): ?float
    {
        $count = count($values);

        if ($count === 0 || $count < $minGroup) {
            return null;
        }

        sort($values);
        $middle = intdiv($count, 2);

        return round($count % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2, 1);
    }
}
