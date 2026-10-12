<?php

namespace Src\Assessment\Interface\Console;

use Illuminate\Console\Command;
use Src\Assessment\Application\Video\PurgeExpiredVideos;

final class PurgeExpiredVideosCommand extends Command
{
    protected $signature = 'assessment:purge-expired-videos';

    protected $description = 'Delete the video answers whose retention period is over (the scores stay)';

    public function handle(PurgeExpiredVideos $purge): int
    {
        $count = $purge->execute();

        $this->info("Deleted {$count} video file".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
