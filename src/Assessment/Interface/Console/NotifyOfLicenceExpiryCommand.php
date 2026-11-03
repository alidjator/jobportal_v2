<?php

namespace Src\Assessment\Interface\Console;

use Illuminate\Console\Command;
use Src\Assessment\Application\Notices\NotifyOfLicenceExpiry;

final class NotifyOfLicenceExpiryCommand extends Command
{
    protected $signature = 'assessment:notify-licence-expiry';

    protected $description = 'Tell psychologists and the admins who manage providers that an STR or SILP ends in 60, 30 or 7 days, or ended';

    public function handle(NotifyOfLicenceExpiry $notify): int
    {
        $count = $notify->execute();

        $this->info("Sent {$count} licence notice".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
