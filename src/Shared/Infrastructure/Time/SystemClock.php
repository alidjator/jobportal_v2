<?php

namespace Src\Shared\Infrastructure\Time;

use DateTimeImmutable;
use Illuminate\Support\Facades\Date;
use Src\Shared\Application\Clock;

/** Reads Laravel's date factory, so `Carbon::setTestNow()` freezes it in tests. */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return Date::now()->toDateTimeImmutable();
    }
}
