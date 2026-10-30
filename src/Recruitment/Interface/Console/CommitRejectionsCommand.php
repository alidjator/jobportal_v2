<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\CommitPendingRejections;

final class CommitRejectionsCommand extends Command
{
    protected $signature = 'recruitment:commit-rejections';

    protected $description = 'Apply the rejections whose undo window has passed';

    public function handle(CommitPendingRejections $commit): int
    {
        $result = $commit->execute();

        $this->info("Committed {$result['committed']} and dropped {$result['dropped']} rejection(s).");

        return self::SUCCESS;
    }
}
