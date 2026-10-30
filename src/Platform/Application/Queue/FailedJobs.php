<?php

namespace Src\Platform\Application\Queue;

/**
 * The failed jobs of one queue that were not reported yet. The classes are counted over the newest rows only (a long run is not read in full); the payload itself is
 * never passed on, because it may hold personal data.
 */
final readonly class FailedJobs
{
    /** @param  array<string, int>  $classes job class to count, most frequent first */
    public function __construct(
        public string $queue,
        public int $count,
        public int $newestId,
        public array $classes,
        public string $newestFailure,
    ) {}
}
