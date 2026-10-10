<?php

namespace Src\TalentPool\Interface\Console;

use Illuminate\Console\Command;
use Src\TalentPool\Application\ClearNotesOfHiddenCandidates;

final class ClearHiddenNotesCommand extends Command
{
    protected $signature = 'talentpool:clear-hidden-notes';

    protected $description = 'Delete the talent pool notes of candidates who can no longer be found';

    public function handle(ClearNotesOfHiddenCandidates $clear): int
    {
        $count = $clear->execute();

        $this->info("Cleared {$count} note".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
