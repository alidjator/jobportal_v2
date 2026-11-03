<?php

namespace Src\Assessment\Domain;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** How long an institution's psychologists take to review a report, in working days (Monday to Friday, Jakarta): `docs/design/phase-8-provider-institutions.md` section 5. */
final class ReviewTarget
{
    public const int MIN_DAYS = 1;

    public const int MAX_DAYS = 10;

    public const int DEFAULT_DAYS = 2;

    private const string TIMEZONE = 'Asia/Jakarta';

    /** @throws InvalidArgumentException when the days are outside the allowed range */
    public static function assertValid(int $days): void
    {
        if ($days < self::MIN_DAYS || $days > self::MAX_DAYS) {
            throw new InvalidArgumentException(sprintf('The review target is %d to %d working days.', self::MIN_DAYS, self::MAX_DAYS));
        }
    }

    /** The day a report submitted at the given moment is due: the target in working days after the day of the submission, as a date (midnight UTC of the Jakarta day). */
    public static function dueOn(DateTimeImmutable $submittedAt, int $days): DateTimeImmutable
    {
        $day = $submittedAt->setTimezone(new DateTimeZone(self::TIMEZONE))->setTime(0, 0);

        for ($counted = 0; $counted < $days;) {
            $day = $day->add(new DateInterval('P1D'));

            if ((int) $day->format('N') < 6) {
                $counted++;
            }
        }

        return new DateTimeImmutable($day->format('Y-m-d'), new DateTimeZone('UTC'));
    }

    /** The Jakarta day of the moment, as `Y-m-d`: a report is overdue when its due day is before it. */
    public static function today(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone(self::TIMEZONE))->format('Y-m-d');
    }

    /** Whether a report that still waits for a review is past its due day. */
    public static function isOverdue(?DateTimeImmutable $dueOn, DateTimeImmutable $now): bool
    {
        return $dueOn !== null && $dueOn->format('Y-m-d') < self::today($now);
    }
}
