<?php

namespace Src\Assessment\Interface\Console;

use Illuminate\Console\Command;
use Src\Assessment\Application\Archive\PurgeArchivedExaminations;

final class PurgeArchivedExaminationsCommand extends Command
{
    protected $signature = 'assessment:purge-archive';

    protected $description = 'Delete the archived raw data of erased psychological examinations whose retention period is over';

    public function handle(PurgeArchivedExaminations $purge): int
    {
        $result = $purge->execute();

        $this->info("Purged {$result['examinations']} archived examination(s) and {$result['files']} file(s).");

        return self::SUCCESS;
    }
}
