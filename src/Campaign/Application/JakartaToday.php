<?php

namespace Src\Campaign\Application;

use DateTimeImmutable;
use DateTimeZone;

/** The calendar day of a moment in Jakarta time (WIB), `Y-m-d`: the day a campaign period is judged on. */
final class JakartaToday
{
    public static function of(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
    }
}
