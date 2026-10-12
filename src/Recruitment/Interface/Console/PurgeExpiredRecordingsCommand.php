<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\Video\PurgeExpiredRecordings;

final class PurgeExpiredRecordingsCommand extends Command
{
    protected $signature = 'recruitment:purge-recordings';

    protected $description = 'Delete interview recordings whose retention period is over and give up on recordings that never finished converting';

    public function handle(PurgeExpiredRecordings $purge): int
    {
        $count = $purge->execute();

        $this->info("Deleted {$count} recording".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
