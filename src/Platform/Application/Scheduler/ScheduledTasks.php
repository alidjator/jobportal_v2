<?php

namespace Src\Platform\Application\Scheduler;

/** The tasks the application schedules now, by their stable names. */
interface ScheduledTasks
{
    /** @return list<string> */
    public function names(): array;
}
