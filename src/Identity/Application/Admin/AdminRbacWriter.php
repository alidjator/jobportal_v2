<?php

namespace Src\Identity\Application\Admin;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** Writes roles and permissions. The RBAC package stays behind this interface (Infrastructure). */
interface AdminRbacWriter
{
    /**
     * Creates the missing permissions and updates the group of existing ones. Permissions that are no longer registered are kept.
     *
     * @param  list<PermissionDefinition>  $definitions
     */
    public function syncPermissions(array $definitions): void;

    /**
     * Creates the role when missing and makes it hold exactly these permissions.
     *
     * @param  list<string>  $permissionNames
     */
    public function syncRole(string $role, array $permissionNames): void;

    public function assignRole(int $adminId, string $role): void;
}
