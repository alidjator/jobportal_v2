<?php

namespace Src\Platform\Interface\Console;

use Illuminate\Console\Command;
use Src\Platform\Application\Queue\CheckQueues;
use Src\Platform\Application\Scheduler\CheckScheduledTasks;

/** Looks for late and failing scheduled tasks, failed queue jobs and stalled queues, and emails the operations address about what turned, and what recovered. */
final class CheckScheduledTasksCommand extends Command
{
    protected $signature = 'platform:check-scheduled-tasks';

    protected $description = 'Email the operations address when a scheduled task turns late or failing, queue jobs fail or a queue stalls, and when they recover';

    public function handle(CheckScheduledTasks $check, CheckQueues $queues): int
    {
        $result = $check->execute();

        $this->info("Alerts raised: {$result['raised']}, recovered: {$result['recovered']}.");

        $queue = $queues->execute();

        $this->info("Queue alerts: failed {$queue['failed']}, stalled {$queue['stalled']}, recovered {$queue['recovered']}.");

        return self::SUCCESS;
    }
}
