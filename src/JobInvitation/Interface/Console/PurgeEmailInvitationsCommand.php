<?php

namespace Src\JobInvitation\Interface\Console;

use Illuminate\Console\Command;
use Src\JobInvitation\Application\PurgeEmailInvitations;

final class PurgeEmailInvitationsCommand extends Command
{
    protected $signature = 'jobinvitation:purge-email-invitations';

    protected $description = 'Expire the invitations by email nobody answered in 30 days and delete their CV, name and email';

    public function handle(PurgeEmailInvitations $purge): int
    {
        $count = $purge->execute();

        $this->info("Changed {$count} invitation(s).");

        return self::SUCCESS;
    }
}
