<?php

namespace Src\Platform\Interface\Console;

use Illuminate\Console\Command;
use Src\Platform\Application\Portal\CheckPortal;

/** Looks for companies the career site has to skip and emails the operations address when the set changes. */
final class CheckPortalCommand extends Command
{
    protected $signature = 'portal:check';

    protected $description = 'Email the operations address when the career site skips a company its settings name, and when it recovers';

    public function handle(CheckPortal $check): int
    {
        $result = $check->execute();

        $this->info("Skipped companies: {$result['skipped']}, email sent: ".($result['notified'] ? 'yes' : 'no').'.');

        return self::SUCCESS;
    }
}
