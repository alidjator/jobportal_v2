<?php

namespace Src\Assessment\Interface\Console;

use Illuminate\Console\Command;
use Src\Assessment\Application\Authoring\PurgeOrphanQuestionImages;

final class PurgeOrphanQuestionImagesCommand extends Command
{
    protected $signature = 'assessment:purge-question-images';

    protected $description = 'Delete the question pictures that were uploaded but that no question named within a day';

    public function handle(PurgeOrphanQuestionImages $purge): int
    {
        $this->info("Purged {$purge->execute()} unused question picture(s).");

        return self::SUCCESS;
    }
}
