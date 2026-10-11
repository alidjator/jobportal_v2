<?php

namespace Src\Matching\Infrastructure;

use Src\Matching\Application\RecommendationQueue;
use Src\Matching\Infrastructure\Jobs\SendRecommendationBatchJob;

final readonly class LaravelRecommendationQueue implements RecommendationQueue
{
    public function dispatchBatch(int $afterId): void
    {
        SendRecommendationBatchJob::dispatch($afterId);
    }
}
