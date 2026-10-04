<?php

namespace Src\Notification\Interface\Console;

use Illuminate\Console\Command;
use Src\Notification\Application\InApp\PurgeExpiredNotifications;

final class PurgeNotificationsCommand extends Command
{
    protected $signature = 'notification:purge-old';

    protected $description = 'Delete in-app notifications older than 6 months';

    public function handle(PurgeExpiredNotifications $purge): int
    {
        $count = $purge->execute();

        $this->info("Deleted {$count} notification".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
