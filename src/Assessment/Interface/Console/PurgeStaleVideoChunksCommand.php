<?php

namespace Src\Assessment\Interface\Console;

use Illuminate\Console\Command;
use Src\Assessment\Application\Video\PurgeStaleVideoChunks;

final class PurgeStaleVideoChunksCommand extends Command
{
    protected $signature = 'assessment:purge-stale-video-chunks';

    protected $description = 'Delete the chunks of video takes left unfinished for more than a day';

    public function handle(PurgeStaleVideoChunks $purge): int
    {
        $count = $purge->execute();

        $this->info("Gave up {$count} unfinished take".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
