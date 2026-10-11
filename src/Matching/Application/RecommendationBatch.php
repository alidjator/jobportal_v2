<?php

namespace Src\Matching\Application;

/** What one batch of the weekly email did: how many emails it sent and the id to continue after, or null when it was the last batch. */
final readonly class RecommendationBatch
{
    public function __construct(public int $sent, public ?int $nextAfterId) {}
}
