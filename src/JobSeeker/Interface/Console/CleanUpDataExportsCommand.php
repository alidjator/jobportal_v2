<?php

namespace Src\JobSeeker\Interface\Console;

use Illuminate\Console\Command;
use Src\JobSeeker\Application\Export\CleanUpDataExports;

final class CleanUpDataExportsCommand extends Command
{
    protected $signature = 'job-seeker:clean-up-data-exports';

    protected $description = 'Delete the personal data archives that are older than 24 hours and close the builds that never finished; safe to run again';

    public function handle(CleanUpDataExports $cleanUp): int
    {
        $this->info('Data exports closed: '.$cleanUp->execute());

        return self::SUCCESS;
    }
}
