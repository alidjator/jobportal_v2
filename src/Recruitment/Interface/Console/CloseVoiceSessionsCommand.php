<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\Voice\CloseVoiceSessions;

final class CloseVoiceSessionsCommand extends Command
{
    protected $signature = 'recruitment:close-voice-sessions';

    protected $description = 'Close the voice bridges of interviews that were cancelled or whose join window ended, hang up their calls and remove the browser accounts';

    public function handle(CloseVoiceSessions $close): int
    {
        $count = $close->execute();

        $this->info("Closed {$count} voice session".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
