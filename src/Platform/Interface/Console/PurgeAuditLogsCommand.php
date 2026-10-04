<?php

namespace Src\Platform\Interface\Console;

use Illuminate\Console\Command;
use Src\Platform\Application\Audit\PurgeExpiredAuditLogs;

final class PurgeAuditLogsCommand extends Command
{
    protected $signature = 'platform:purge-audit-logs';

    protected $description = 'Delete audit log entries older than 12 months and record the purge';

    public function handle(PurgeExpiredAuditLogs $purge): int
    {
        $count = $purge->execute();

        $this->info("Deleted {$count} audit log entr".($count === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
