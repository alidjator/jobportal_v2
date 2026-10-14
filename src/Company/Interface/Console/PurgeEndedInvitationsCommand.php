<?php

namespace Src\Company\Interface\Console;

use Illuminate\Console\Command;
use Src\Company\Application\Invitations\PurgeEndedInvitations;

final class PurgeEndedInvitationsCommand extends Command
{
    protected $signature = 'company:purge-invitations';

    protected $description = 'Delete the company invitations that ended (expired, revoked or accepted) more than 30 days ago';

    public function handle(PurgeEndedInvitations $purge): int
    {
        $count = $purge->execute();

        $this->info("Deleted {$count} invitation(s).");

        return self::SUCCESS;
    }
}
