<?php

namespace Src\Job\Interface\Console;

use Illuminate\Console\Command;
use Src\Job\Application\ExpireDueJobs;

final class ExpireDueJobsCommand extends Command
{
    protected $signature = 'job:expire-due';

    protected $description = 'Set live jobs whose deadline day is over (Jakarta time) to expired';

    public function handle(ExpireDueJobs $expire): int
    {
        $count = $expire->execute();

        $this->info("Expired {$count} job".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
