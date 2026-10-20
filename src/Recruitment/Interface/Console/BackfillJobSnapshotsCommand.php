<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\BackfillJobSnapshots;

/** Run once after the migration that added the copy of the job content to the applications; running it again fills only what is still missing. */
final class BackfillJobSnapshotsCommand extends Command
{
    protected $signature = 'recruitment:backfill-job-snapshots';

    protected $description = 'Fill the copy of the job content of the applications that have none (from the job as it is now)';

    public function handle(BackfillJobSnapshots $backfill): int
    {
        $count = $backfill->execute();

        $this->info("Filled {$count} application".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
