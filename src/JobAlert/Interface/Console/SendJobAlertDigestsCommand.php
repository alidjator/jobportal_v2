<?php

namespace Src\JobAlert\Interface\Console;

use Illuminate\Console\Command;
use Src\JobAlert\Application\SendJobAlertDigests;

final class SendJobAlertDigestsCommand extends Command
{
    protected $signature = 'jobalert:send-digests';

    protected $description = 'Send the daily (08:00) and weekly (Monday 08:00, Jakarta time) job alert summaries that are due; safe to run again';

    public function handle(SendJobAlertDigests $send): int
    {
        $this->info('Job alert summaries sent: '.$send->execute());

        return self::SUCCESS;
    }
}
