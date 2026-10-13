<?php

namespace Src\Identity\Application\Admin;

use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Permissions\PermissionDefinition;

/** The permissions the contexts register, which a role may hold, for the operator. */
final readonly class ListAdminPermissions
{
    public function __construct(private PermissionCatalog $catalog) {}

    /** @return list<PermissionDefinition> sorted by name */
    public function execute(): array
    {
        return $this->catalog->all();
    }
}
