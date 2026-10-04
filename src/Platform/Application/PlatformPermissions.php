<?php

namespace Src\Platform\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the Platform context, registered in the shared catalog by its service provider. */
final class PlatformPermissions
{
    public const string GROUP = 'platform';

    public const string AUDIT_LOGS_VIEW = 'audit_logs.view';

    public const string SETTINGS_MANAGE = 'settings.manage';

    public const string EMAIL_TEMPLATES_MANAGE = 'email_templates.manage';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [
            new PermissionDefinition(self::AUDIT_LOGS_VIEW, self::GROUP),
            new PermissionDefinition(self::SETTINGS_MANAGE, self::GROUP),
            new PermissionDefinition(self::EMAIL_TEMPLATES_MANAGE, self::GROUP),
        ];
    }
}
