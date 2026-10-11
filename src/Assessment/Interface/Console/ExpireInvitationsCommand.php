<?php

namespace Src\Assessment\Interface\Console;

use Illuminate\Console\Command;
use Src\Assessment\Application\Delivery\ExpireInvitations;

final class ExpireInvitationsCommand extends Command
{
    protected $signature = 'assessment:expire-invitations';

    protected $description = 'Close the test invitations that were not started before their due time and tell the candidates';

    public function handle(ExpireInvitations $expire): int
    {
        $count = $expire->execute();

        $this->info("Expired {$count} invitation".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
