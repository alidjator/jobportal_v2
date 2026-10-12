<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\Video\CloseVideoRooms;

final class CloseVideoRoomsCommand extends Command
{
    protected $signature = 'recruitment:close-video-rooms';

    protected $description = 'Close the video rooms of interviews that were cancelled or whose join window ended, and remove their join tokens';

    public function handle(CloseVideoRooms $close): int
    {
        $count = $close->execute();

        $this->info("Closed {$count} video room".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
