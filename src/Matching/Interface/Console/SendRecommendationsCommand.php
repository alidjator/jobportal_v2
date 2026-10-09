<?php

namespace Src\Matching\Interface\Console;

use Illuminate\Console\Command;
use Src\Matching\Application\SendRecommendations;

final class SendRecommendationsCommand extends Command
{
    protected $signature = 'matching:send-recommendations';

    protected $description = 'Email each job seeker the best new jobs that fit their profile (scheduled Monday 08:00 Jakarta time); nobody gets two emails within six days';

    public function handle(SendRecommendations $send): int
    {
        $this->info('Recommendation emails sent: '.$send->execute());

        return self::SUCCESS;
    }
}
