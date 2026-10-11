<?php

namespace Src\Matching\Interface\Console;

use Illuminate\Console\Command;
use Src\Matching\Application\RecommendationQueue;

final class SendRecommendationsCommand extends Command
{
    protected $signature = 'matching:send-recommendations';

    protected $description = 'Queue the weekly recommendation emails in batches on the bulk queue (scheduled Monday 08:00 Jakarta time); nobody gets two emails within six days';

    public function handle(RecommendationQueue $queue): int
    {
        $queue->dispatchBatch(0);

        $this->info('Recommendation emails queued.');

        return self::SUCCESS;
    }
}
