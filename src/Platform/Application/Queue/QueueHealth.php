<?php

namespace Src\Platform\Application\Queue;

use DateTimeImmutable;

/** Reads the `jobs` and `failed_jobs` tables. */
interface QueueHealth
{
    /** @return list<QueueStatus> a queue with waiting jobs or failures in the last 24 hours, by queue name */
    public function queues(DateTimeImmutable $now): array;

    /** @return list<string> the queues that have at least one failed job, by name */
    public function failedQueues(): array;

    /** The failed jobs of the queue newer than the given job id, or null when there are none. */
    public function failedSince(string $queue, int $afterId): ?FailedJobs;
}
