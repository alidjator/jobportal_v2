<?php

namespace Src\Platform\Application\Queue;

use DateTimeImmutable;

interface QueueAlertRepository
{
    /** @return list<QueueAlert> */
    public function all(): array;

    /** Marks an alert as sent and open. `$lastFailedJobId` (null: keep) is the newest failed job the email covered. */
    public function markAlerted(QueueAlertKind $kind, string $queue, DateTimeImmutable $at, ?int $lastFailedJobId = null): void;

    public function markRecovered(QueueAlertKind $kind, string $queue, DateTimeImmutable $at): void;
}
