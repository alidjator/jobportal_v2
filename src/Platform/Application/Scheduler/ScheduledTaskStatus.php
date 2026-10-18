<?php

namespace Src\Platform\Application\Scheduler;

use DateTimeImmutable;

final readonly class ScheduledTaskStatus
{
    public function __construct(
        public string $task,
        public ?DateTimeImmutable $lastFinishedAt,
        public bool $late,
        public bool $failing,
        public ?string $lastFailure,
        public ?DateTimeImmutable $alertedAt,
    ) {}

    public function unhealthy(): bool
    {
        return $this->late || $this->failing;
    }
}
