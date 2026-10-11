<?php

namespace Src\Assessment\Interface\Console;

use Illuminate\Console\Command;
use Src\Assessment\Application\Delivery\SendInvitationReminders;

final class SendInvitationRemindersCommand extends Command
{
    protected $signature = 'assessment:send-invitation-reminders';

    protected $description = 'Remind candidates of tests they have not started that are due within 24 hours';

    public function handle(SendInvitationReminders $remind): int
    {
        $count = $remind->execute();

        $this->info("Reminded {$count} invitation".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
