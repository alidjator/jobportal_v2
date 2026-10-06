<?php

namespace Src\Job\Application;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A job accepts applications until the end of its deadline day in Jakarta time (WIB). Deadlines are plain dates, kept as midnight UTC of that date, so
 * "today" is the date it is now in Jakarta, as the same kind of value and comparable with a deadline.
 */
final class JakartaDate
{
    public const string TIME_ZONE = 'Asia/Jakarta';

    public static function today(DateTimeImmutable $now): DateTimeImmutable
    {
        $date = $now->setTimezone(new DateTimeZone(self::TIME_ZONE))->format('Y-m-d');

        return new DateTimeImmutable($date, new DateTimeZone('UTC'));
    }
}
