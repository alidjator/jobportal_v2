<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\SendReviewNotices;

final class SendReviewNoticesCommand extends Command
{
    protected $signature = 'recruitment:send-review-notices';

    protected $description = 'Remind the reviewers the day before a shortlist review is due, and tell the requester of a review that passed its due date';

    public function handle(SendReviewNotices $notices): int
    {
        $sent = $notices->execute();

        $this->info("Reminded {$sent['reminded']} review request".($sent['reminded'] === 1 ? '' : 's').", told the requester of {$sent['overdue']} overdue one".($sent['overdue'] === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
