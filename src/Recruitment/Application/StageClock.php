<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\StageType;

/** Time in stage in whole Jakarta days (`docs/design/phase-8-recruiter-workday.md` section 2), and the stale mark of an application that waits too long in its first two stages. */
final class StageClock
{
    public const string ZONE = 'Asia/Jakarta';

    /** Whole Jakarta days from the day the application entered its stage to the day of `$now` (never negative). */
    public static function days(DateTimeImmutable $enteredAt, DateTimeImmutable $now): int
    {
        $zone = new DateTimeZone(self::ZONE);
        $entered = new DateTimeImmutable($enteredAt->setTimezone($zone)->format('Y-m-d'));
        $today = new DateTimeImmutable($now->setTimezone($zone)->format('Y-m-d'));

        return $today < $entered ? 0 : (int) $entered->diff($today)->days;
    }

    /** Stale: more days in `applied` or `screening` than the company allows. Later stages have their own clocks (interviews, tests, offers). */
    public static function isStale(StageType $stage, int $days, int $limit): bool
    {
        return ($stage === StageType::Applied || $stage === StageType::Screening) && $days > $limit;
    }
}
