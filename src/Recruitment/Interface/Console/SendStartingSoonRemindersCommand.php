<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\SendStartingSoonReminders;

final class SendStartingSoonRemindersCommand extends Command
{
    protected $signature = 'recruitment:send-starting-soon-reminders';

    protected $description = 'Tell the interviewers and panels in-app that a confirmed interview starts within 30 minutes, the job seekers that it starts within 2 hours, and let unanswered proposals lapse';

    public function handle(SendStartingSoonReminders $remind): int
    {
        $lapsed = $remind->lapseProposals();
        $count = $remind->execute();
        $candidates = $remind->remindCandidates();

        $this->info("Told {$count} panel".($count === 1 ? '' : 's').' that an interview starts soon.');
        $this->info("Told {$candidates} job seeker".($candidates === 1 ? '' : 's').' that an interview starts soon.');
        $this->info("{$lapsed} unanswered proposal".($lapsed === 1 ? '' : 's').' lapsed.');

        return self::SUCCESS;
    }
}
