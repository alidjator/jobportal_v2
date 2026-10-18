<?php

namespace Src\Platform\Application\Scheduler;

use DateTimeImmutable;

/** What the platform remembers about one scheduled task: when it last started, finished and failed, and whether an alert for it is open. */
final readonly class ScheduledTaskRun
{
    public function __construct(
        public string $task,
        public string $expression,
        public ?DateTimeImmutable $lastStartedAt,
        public ?DateTimeImmutable $lastFinishedAt,
        public ?DateTimeImmutable $lastFailedAt,
        public ?string $lastFailure,
        public int $consecutiveFailures,
        public ?DateTimeImmutable $alertedAt,
    ) {}
}
