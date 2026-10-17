<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\SendStartingSoonReminders;

final class SendStartingSoonRemindersCommand extends Command
{
    protected $signature = 'recruitment:send-starting-soon-reminders';

    protected $description = 'Tell the interviewers and panels in-app that a confirmed interview starts within 30 minutes';

    public function handle(SendStartingSoonReminders $remind): int
    {
        $count = $remind->execute();

        $this->info("Told {$count} panel".($count === 1 ? '' : 's').' that an interview starts soon.');

        return self::SUCCESS;
    }
}
