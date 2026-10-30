<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\Checks\PurgeCheckResults;

final class PurgeCheckResultsCommand extends Command
{
    protected $signature = 'recruitment:purge-check-results';

    protected $description = 'Delete the medical outcome and the result files of the checks of applications that ended more than 90 days ago';

    public function handle(PurgeCheckResults $purge): int
    {
        $count = $purge->execute();

        $this->info("Deleted the result of {$count} check".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
