<?php

namespace Src\Platform\Application\Scheduler;

use DateTimeImmutable;

/** `lastTickAt` is when the scheduler last ran its every-minute heartbeat (null before the first one); `tasks` lists late and failing tasks first. */
final readonly class SchedulerReport
{
    /** @param  list<ScheduledTaskStatus>  $tasks */
    public function __construct(
        public ?DateTimeImmutable $lastTickAt,
        public array $tasks,
    ) {}
}
