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
}
