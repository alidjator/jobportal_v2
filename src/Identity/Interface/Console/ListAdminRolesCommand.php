<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Admin\AdminRoleSummary;
use Src\Identity\Application\Admin\ListAdminRoles;

final class ListAdminRolesCommand extends Command
{
    protected $signature = 'identity:list-roles';

    protected $description = 'List the admin roles with their permissions and the number of administrators holding each';

    public function handle(ListAdminRoles $list): int
    {
        $this->table(['Role', 'Administrators', 'Permissions'], array_map(
            fn (AdminRoleSummary $role): array => [$role->name, $role->adminCount, implode("\n", $role->permissions)],
            $list->execute(),
        ));

        return self::SUCCESS;
    }
}
