<?php

namespace Src\JobSeeker\Interface\Console;

use Illuminate\Console\Command;
use Src\JobSeeker\Application\Reminders\SendProfileReminders;

final class SendProfileRemindersCommand extends Command
{
    protected $signature = 'job-seeker:send-profile-reminders';

    protected $description = 'Email the job seekers whose profile is not yet complete enough to apply (1 day after the first sign-in, then every 7 days, at most 3); safe to run again';

    public function handle(SendProfileReminders $send): int
    {
        $this->info('Profile reminders sent: '.$send->execute());

        return self::SUCCESS;
    }
}
