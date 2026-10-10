<?php

namespace Src\Newsletter\Interface\Console;

use Illuminate\Console\Command;
use Src\Newsletter\Application\PurgePendingSubscribers;

final class PurgePendingSubscribersCommand extends Command
{
    protected $signature = 'newsletter:purge-pending';

    protected $description = 'Delete the newsletter addresses that never confirmed (the link expired more than 7 days ago); safe to run again';

    public function handle(PurgePendingSubscribers $purge): int
    {
        $this->info('Pending subscribers deleted: '.$purge->execute());

        return self::SUCCESS;
    }
}
