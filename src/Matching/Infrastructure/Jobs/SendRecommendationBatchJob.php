<?php

namespace Src\Matching\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Src\Matching\Application\RecommendationQueue;
use Src\Matching\Application\SendRecommendationBatch;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * One batch of the weekly email on the `bulk` queue; it queues the next batch when it is done. Running a batch again is safe: nobody gets two emails within
 * six days, so a retry sends only to the people the failed run did not reach.
 */
final class SendRecommendationBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 540;

    public function __construct(public readonly int $afterId)
    {
        $this->onQueue(QueueName::Bulk->value);
        $this->onConnection(QueueName::Bulk->connection());
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(SendRecommendationBatch $batch, RecommendationQueue $queue): void
    {
        $result = $batch->execute($this->afterId);

        if ($result->nextAfterId !== null) {
            $queue->dispatchBatch($result->nextAfterId);
        }
    }
}
