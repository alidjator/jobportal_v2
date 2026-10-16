<?php

namespace Src\Organization\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the Organization context, registered in the shared catalog by its service provider. */
final class OrganizationPermissions
{
    public const string GROUP = 'organization';

    /** Receiving temporary support access to customer groups (`erp-governance.md` section 1.1); it opens nothing by itself. */
    public const string GROUPS_SUPPORT = 'groups.support';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [
            new PermissionDefinition(self::GROUPS_SUPPORT, self::GROUP),
        ];
    }
}
