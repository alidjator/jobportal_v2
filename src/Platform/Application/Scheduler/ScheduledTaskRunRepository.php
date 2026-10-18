<?php

namespace Src\Platform\Application\Scheduler;

use DateTimeImmutable;

interface ScheduledTaskRunRepository
{
    public function recordStarted(string $task, string $expression, DateTimeImmutable $at): void;

    /** A finish clears the failure streak. */
    public function recordFinished(string $task, string $expression, DateTimeImmutable $at): void;

    public function recordFailed(string $task, string $expression, DateTimeImmutable $at, string $failure): void;

    /** @return list<ScheduledTaskRun> by task name */
    public function all(): array;

    /** Sets (or, with null, clears) the time the alert for the task was sent. */
    public function markAlerted(string $task, ?DateTimeImmutable $at): void;

    /** @param  list<string>  $tasks */
    public function forgetExcept(array $tasks): void;
}
