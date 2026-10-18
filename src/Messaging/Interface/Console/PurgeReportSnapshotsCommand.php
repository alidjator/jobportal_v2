<?php

namespace Src\Messaging\Interface\Console;

use Illuminate\Console\Command;
use Src\Messaging\Application\Reports\PurgeReportSnapshots;

/** Clears the message snapshots of conversation reports decided more than 180 days ago. */
final class PurgeReportSnapshotsCommand extends Command
{
    protected $signature = 'messaging:purge-report-snapshots';

    protected $description = 'Clear the message snapshots of chat reports decided more than 180 days ago';

    public function handle(PurgeReportSnapshots $purge): int
    {
        $this->info("Snapshots cleared: {$purge->execute()}.");

        return self::SUCCESS;
    }
}
