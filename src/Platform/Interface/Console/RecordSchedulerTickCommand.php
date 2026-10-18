<?php

namespace Src\Platform\Interface\Console;

use Illuminate\Console\Command;

/** Does nothing: it runs every minute so the scheduler watch (and the outside uptime monitor) can tell the scheduler is alive from its finish time. */
final class RecordSchedulerTickCommand extends Command
{
    protected $signature = 'platform:record-scheduler-tick';

    protected $description = 'Heartbeat of the scheduler: runs every minute so a dead scheduler can be noticed';

    protected $hidden = true;

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
