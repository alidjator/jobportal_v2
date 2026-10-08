<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\ProcessOfferDeadlines;

final class ProcessOfferDeadlinesCommand extends Command
{
    protected $signature = 'recruitment:process-offer-deadlines';

    protected $description = 'Expire job offers past their deadline and warn job seekers whose offer expires within 24 hours';

    public function handle(ProcessOfferDeadlines $process): int
    {
        $result = $process->execute();

        $this->info("Expired {$result['expired']} and reminded {$result['reminded']} offer(s).");

        return self::SUCCESS;
    }
}
