<?php

namespace Src\Platform\Application\Scheduler;

use Cron\CronExpression;
use DateTimeImmutable;
use Src\Shared\Application\Clock;

/**
 * Reads the scheduler watch. A task is **late** when it has not finished within two intervals of its expression plus 5 minutes (counted from its last finish, or its
 * first start while it never finished), **failing** while its last run failed. The interval of an expression is the longest gap among its next runs (a weekday task
 * waits longer over a weekend). Tasks no longer in the schedule are ignored. Late and failing tasks come first.
 */
final readonly class ReadScheduler
{
    public const string HEARTBEAT = 'platform:record-scheduler-tick';

    private const int GRACE_SECONDS = 300;

    private const int SAMPLE_RUNS = 8;

    public function __construct(
        private ScheduledTaskRunRepository $runs,
        private ScheduledTasks $scheduled,
        private Clock $clock,
    ) {}

    public function execute(): SchedulerReport
    {
        $now = $this->clock->now();
        $current = array_flip($this->scheduled->names());
        $tasks = [];
        $tick = null;

        foreach ($this->runs->all() as $run) {
            if (! isset($current[$run->task])) {
                continue;
            }

            if ($run->task === self::HEARTBEAT) {
                $tick = $run->lastFinishedAt;
            }

            $tasks[] = new ScheduledTaskStatus($run->task, $run->lastFinishedAt, $this->isLate($run, $now), $run->consecutiveFailures > 0, $run->lastFailure, $run->alertedAt);
        }

        usort($tasks, fn (ScheduledTaskStatus $a, ScheduledTaskStatus $b): int => [$b->unhealthy(), $a->task] <=> [$a->unhealthy(), $b->task]);

        return new SchedulerReport($tick, $tasks);
    }

    private function isLate(ScheduledTaskRun $run, DateTimeImmutable $now): bool
    {
        $since = $run->lastFinishedAt ?? $run->lastStartedAt;

        if ($since === null) {
            return false;
        }

        return $now->getTimestamp() - $since->getTimestamp() > 2 * $this->interval($run->expression, $now) + self::GRACE_SECONDS;
    }

    private function interval(string $expression, DateTimeImmutable $now): int
    {
        $cron = new CronExpression($expression);
        $previous = $cron->getNextRunDate($now);
        $longest = 0;

        foreach ($cron->getMultipleRunDates(self::SAMPLE_RUNS, $previous) as $next) {
            $longest = max($longest, $next->getTimestamp() - $previous->getTimestamp());
            $previous = $next;
        }

        return max($longest, 60);
    }
}
