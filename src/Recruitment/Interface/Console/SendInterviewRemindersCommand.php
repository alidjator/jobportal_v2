<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\SendInterviewReminders;
use Src\Recruitment\Application\SendScorecardReminders;

final class SendInterviewRemindersCommand extends Command
{
    protected $signature = 'recruitment:send-interview-reminders';

    protected $description = 'Remind job seekers and teams of confirmed interviews that start within 24 hours, and panels of scorecards still missing a day after their interview';

    public function handle(SendInterviewReminders $remind, SendScorecardReminders $scorecards): int
    {
        $count = $remind->execute();

        $this->info("Reminded {$count} interview".($count === 1 ? '' : 's').'.');

        $panels = $scorecards->execute();

        if ($panels > 0) {
            $this->info("Reminded {$panels} panel".($panels === 1 ? '' : 's').' of missing scorecards.');
        }

        return self::SUCCESS;
    }
}
