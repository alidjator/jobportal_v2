<?php

namespace Src\Platform\Application\Queue;

use DateTimeImmutable;

/** What was told about one queue: an alert is open while `recoveredAt` is null. `lastFailedJobId` is the newest failed job that an email already covered. */
final readonly class QueueAlert
{
    public function __construct(
        public QueueAlertKind $kind,
        public string $queue,
        public ?DateTimeImmutable $alertedAt,
        public ?DateTimeImmutable $recoveredAt,
        public int $lastFailedJobId,
    ) {}

    public function open(): bool
    {
        return $this->alertedAt !== null && $this->recoveredAt === null;
    }
}
