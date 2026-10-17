<?php

namespace Src\Dashboard\Interface\Console;

use Illuminate\Console\Command;
use Src\Dashboard\Application\SendTeamDigests;

final class SendTeamDigestsCommand extends Command
{
    protected $signature = 'company:send-team-digests';

    protected $description = 'Email the daily digest (task list, new applications and interviews of the day) to the company members who want it';

    public function handle(SendTeamDigests $digests): int
    {
        $count = $digests->execute();

        $this->info("Sent {$count} digest".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
