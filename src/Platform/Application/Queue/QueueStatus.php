<?php

namespace Src\Platform\Application\Queue;

/** One queue as the database sees it. `oldestWaitSeconds` is the wait of the oldest job that is due and not reserved (null when none is due). */
final readonly class QueueStatus
{
    /** A queue whose oldest due job waits longer than this has stalled: its workers are dead or it is full. */
    public const int STALLED_AFTER_SECONDS = 600;

    public function __construct(
        public string $queue,
        public int $waiting,
        public ?int $oldestWaitSeconds,
        public int $failedLast24Hours,
    ) {}

    public function stalled(): bool
    {
        return $this->oldestWaitSeconds !== null && $this->oldestWaitSeconds > self::STALLED_AFTER_SECONDS;
    }
}
