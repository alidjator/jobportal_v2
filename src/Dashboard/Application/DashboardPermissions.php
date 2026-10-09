<?php

namespace Src\Dashboard\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the Dashboard context, registered in the shared catalog by its service provider. */
final class DashboardPermissions
{
    public const string GROUP = 'dashboard';

    public const string DASHBOARD_VIEW = 'dashboard.view';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [
            new PermissionDefinition(self::DASHBOARD_VIEW, self::GROUP),
        ];
    }
}
