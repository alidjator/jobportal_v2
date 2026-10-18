<?php

namespace Src\Platform\Interface\Console;

use Illuminate\Console\Command;
use Src\Platform\Application\Scheduler\CheckScheduledTasks;

/** Looks for late and failing scheduled tasks and emails the operations address about the ones that turned, and the ones that recovered. */
final class CheckScheduledTasksCommand extends Command
{
    protected $signature = 'platform:check-scheduled-tasks';

    protected $description = 'Email the operations address when a scheduled task turns late or failing, and when it recovers';

    public function handle(CheckScheduledTasks $check): int
    {
        $result = $check->execute();

        $this->info("Alerts raised: {$result['raised']}, recovered: {$result['recovered']}.");

        return self::SUCCESS;
    }
}
