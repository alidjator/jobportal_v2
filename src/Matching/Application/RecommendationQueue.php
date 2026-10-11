<?php

namespace Src\Matching\Application;

/** Queues the next batch of the weekly email. */
interface RecommendationQueue
{
    /** Queues the batch of job seekers whose account id is greater than the given one (0 starts a run). */
    public function dispatchBatch(int $afterId): void;
}
