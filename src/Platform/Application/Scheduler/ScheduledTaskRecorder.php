<?php

namespace Src\Platform\Application\Scheduler;

use Src\Shared\Application\Clock;

/** Fills the scheduler watch from the scheduler's own events. */
final readonly class ScheduledTaskRecorder
{
    private const int FAILURE_MAX = 1000;

    public function __construct(private ScheduledTaskRunRepository $runs, private Clock $clock) {}

    public function started(string $task, string $expression): void
    {
        $this->runs->recordStarted($task, $expression, $this->clock->now());
    }

    public function finished(string $task, string $expression): void
    {
        $this->runs->recordFinished($task, $expression, $this->clock->now());
    }

    public function failed(string $task, string $expression, string $failure): void
    {
        $this->runs->recordFailed($task, $expression, $this->clock->now(), mb_substr($failure, 0, self::FAILURE_MAX));
    }
}
