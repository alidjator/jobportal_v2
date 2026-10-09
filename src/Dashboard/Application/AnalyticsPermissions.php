<?php

namespace Src\Dashboard\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the analytics, registered in the shared catalog by the service provider of the Dashboard context. */
final class AnalyticsPermissions
{
    public const string GROUP = 'analytics';

    public const string ANALYTICS_VIEW = 'analytics.view';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [
            new PermissionDefinition(self::ANALYTICS_VIEW, self::GROUP),
        ];
    }
}
