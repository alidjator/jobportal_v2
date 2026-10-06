<?php

namespace Src\JobSeeker\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Application\Clock;

/** Today, this month and this year as they are in Jakarta (WIB). Dates are kept as midnight UTC of the same calendar date, so they compare with stored dates. */
final readonly class JakartaToday
{
    private const string TIME_ZONE = 'Asia/Jakarta';

    public function __construct(private Clock $clock) {}

    public function date(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->clock->now()->setTimezone(new DateTimeZone(self::TIME_ZONE))->format('Y-m-d'), new DateTimeZone('UTC'));
    }

    /** The first day of the current month. */
    public function month(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->clock->now()->setTimezone(new DateTimeZone(self::TIME_ZONE))->format('Y-m-01'), new DateTimeZone('UTC'));
    }

    public function year(): int
    {
        return (int) $this->clock->now()->setTimezone(new DateTimeZone(self::TIME_ZONE))->format('Y');
    }
}
