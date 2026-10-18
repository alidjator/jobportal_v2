<?php

namespace Src\Platform\Application\Scheduler;

use Src\Shared\Application\Clock;

/** Whether the scheduler is alive: its heartbeat finished within the last 5 minutes. For the outside uptime monitor; it reveals no data. */
final readonly class CheckSchedulerHealth
{
    public const int MAX_AGE_SECONDS = 300;

    public function __construct(private ReadScheduler $scheduler, private Clock $clock) {}

    public function isAlive(): bool
    {
        $tick = $this->scheduler->execute()->lastTickAt;

        return $tick !== null && $this->clock->now()->getTimestamp() - $tick->getTimestamp() <= self::MAX_AGE_SECONDS;
    }
}
