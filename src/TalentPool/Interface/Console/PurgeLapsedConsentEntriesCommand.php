<?php

namespace Src\TalentPool\Interface\Console;

use Illuminate\Console\Command;
use Src\TalentPool\Application\PurgeLapsedConsentEntries;

final class PurgeLapsedConsentEntriesCommand extends Command
{
    protected $signature = 'talentpool:purge-lapsed-consent';

    protected $description = 'Delete the talent pool entries whose only basis was a consent older than 24 months';

    public function handle(PurgeLapsedConsentEntries $purge): int
    {
        $count = $purge->execute();

        $this->info("Deleted {$count} entr".($count === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
