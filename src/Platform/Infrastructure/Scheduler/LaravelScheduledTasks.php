<?php

namespace Src\Platform\Infrastructure\Scheduler;

use Illuminate\Console\Scheduling\Schedule;
use Src\Platform\Application\Scheduler\ScheduledTasks;

/** The scheduled tasks of the application, read from Laravel's schedule. */
final readonly class LaravelScheduledTasks implements ScheduledTasks
{
    public function __construct(private Schedule $schedule) {}

    public function names(): array
    {
        return array_values(array_unique(array_map(ScheduledTaskName::of(...), $this->schedule->events())));
    }
}
