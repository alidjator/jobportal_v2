<?php

namespace Src\Job\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Src\Job\Application\Views\JobViewLog;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Writes one counted view on the `bulk` queue, so the public request never waits for it. A view lost after the retries is only one number less. */
final class WriteJobView implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $jobId,
        public readonly string $viewerKey,
        public readonly string $day,
    ) {
        $this->onQueue(QueueName::Bulk->value);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(JobViewLog $log): void
    {
        $log->add($this->jobId, $this->viewerKey, $this->day);
    }
}
