<?php

namespace Src\Recruitment\Domain\Video;

use DateInterval;
use DateTimeImmutable;
use Src\Recruitment\Domain\Interview;

/** When the video room of an interview is open: from {@see self::MINUTES_BEFORE} minutes before it starts until {@see self::MINUTES_AFTER} minutes after it ends. */
final class VideoJoinWindow
{
    public const int MINUTES_BEFORE = 10;

    public const int MINUTES_AFTER = 30;

    public static function opensAt(Interview $interview): DateTimeImmutable
    {
        return $interview->startsAt->sub(new DateInterval('PT'.self::MINUTES_BEFORE.'M'));
    }

    public static function closesAt(Interview $interview): DateTimeImmutable
    {
        return $interview->endsAt->add(new DateInterval('PT'.self::MINUTES_AFTER.'M'));
    }

    public static function isOpen(Interview $interview, DateTimeImmutable $now): bool
    {
        return $now >= self::opensAt($interview) && $now <= self::closesAt($interview);
    }
}
