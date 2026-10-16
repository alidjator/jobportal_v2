<?php

namespace Src\Approval\Interface\Console;

use Illuminate\Console\Command;
use Src\Approval\Application\Instance\SendApprovalReminders;

final class SendApprovalRemindersCommand extends Command
{
    protected $signature = 'approvals:send-reminders';

    protected $description = 'Remind the approvers of the approvals that have waited too long for a decision';

    public function handle(SendApprovalReminders $reminders): int
    {
        $count = $reminders->execute();

        $this->info("Reminded about {$count} approval".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
