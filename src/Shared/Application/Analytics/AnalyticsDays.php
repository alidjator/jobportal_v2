<?php

namespace Src\Shared\Application\Analytics;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/** Jakarta calendar days as UTC time bounds. Jakarta is UTC+7 all year. */
final class AnalyticsDays
{
    /** The UTC moment (`Y-m-d H:i:s`) a Jakarta day starts. */
    public static function startUtc(string $day): string
    {
        return (new DateTimeImmutable($day, new DateTimeZone('Asia/Jakarta')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** The UTC moment (`Y-m-d H:i:s`) the day after the Jakarta day starts: the end of the day, not included. */
    public static function endUtc(string $day): string
    {
        return (new DateTimeImmutable($day, new DateTimeZone('Asia/Jakarta')))->add(new DateInterval('P1D'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** The Jakarta day (`Y-m-d`) of a UTC timestamp read from the database. */
    public static function dayOf(string $utc): string
    {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
    }

    /** The number of days from one `Y-m-d` day to another, both included. */
    public static function count(string $from, string $to): int
    {
        $zone = new DateTimeZone('UTC');

        return (int) (new DateTimeImmutable($from, $zone))->diff(new DateTimeImmutable($to, $zone))->days + 1;
    }
}
