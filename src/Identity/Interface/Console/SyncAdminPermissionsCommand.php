<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Admin\SyncAdminPermissions;

final class SyncAdminPermissionsCommand extends Command
{
    protected $signature = 'identity:sync-permissions';

    protected $description = 'Create the admin permissions registered by the contexts and give super_admin all of them';

    public function handle(SyncAdminPermissions $sync): int
    {
        $count = $sync->execute();

        $this->info("Synchronised {$count} permission(s).");

        return self::SUCCESS;
    }
}
