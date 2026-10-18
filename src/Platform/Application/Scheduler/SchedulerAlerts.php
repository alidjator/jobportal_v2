<?php

namespace Src\Platform\Application\Scheduler;

/** Tells the operations address about the scheduler. One email per call, listing every task of the call. */
interface SchedulerAlerts
{
    /** @param  list<ScheduledTaskStatus>  $tasks tasks that turned late or failing */
    public function raised(string $email, array $tasks): void;

    /** @param  list<string>  $tasks names of tasks that are healthy again */
    public function recovered(string $email, array $tasks): void;
}
