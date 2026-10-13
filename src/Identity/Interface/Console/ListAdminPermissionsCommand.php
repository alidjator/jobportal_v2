<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Admin\ListAdminPermissions;
use Src\Shared\Application\Permissions\PermissionDefinition;

final class ListAdminPermissionsCommand extends Command
{
    protected $signature = 'identity:list-permissions';

    protected $description = 'List the admin permissions a role may hold';

    public function handle(ListAdminPermissions $list): int
    {
        $this->table(['Permission', 'Group'], array_map(
            fn (PermissionDefinition $definition): array => [$definition->name, $definition->group],
            $list->execute(),
        ));

        return self::SUCCESS;
    }
}
