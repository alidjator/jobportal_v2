<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use DateTimeZone;

/** The first Jakarta day a rejected applicant may apply to the same job again (`phase-8-rejections.md` section 3): the day of the rejection plus the days the job sets. */
final class ReapplyWindow
{
    private const string TIME_ZONE = 'Asia/Jakarta';

    public static function opensOn(DateTimeImmutable $rejectedAt, int $days): DateTimeImmutable
    {
        $zone = new DateTimeZone(self::TIME_ZONE);

        return (new DateTimeImmutable($rejectedAt->setTimezone($zone)->format('Y-m-d'), $zone))->modify("+{$days} days");
    }
}
