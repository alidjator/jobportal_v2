<?php

namespace Src\Assessment\Interface\Console;

use Illuminate\Console\Command;
use Src\Assessment\Application\Attempts\AutoSubmitAttempts;

final class AutoSubmitAttemptsCommand extends Command
{
    protected $signature = 'assessment:auto-submit-attempts';

    protected $description = 'Submit the test attempts whose time has run out and tell the team';

    public function handle(AutoSubmitAttempts $submit): int
    {
        $count = $submit->execute();

        $this->info("Submitted {$count} attempt".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
