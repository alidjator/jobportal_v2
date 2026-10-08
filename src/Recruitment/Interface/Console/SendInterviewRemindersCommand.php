<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\SendInterviewReminders;

final class SendInterviewRemindersCommand extends Command
{
    protected $signature = 'recruitment:send-interview-reminders';

    protected $description = 'Remind job seekers and teams of confirmed interviews that start within 24 hours';

    public function handle(SendInterviewReminders $remind): int
    {
        $count = $remind->execute();

        $this->info("Reminded {$count} interview".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
