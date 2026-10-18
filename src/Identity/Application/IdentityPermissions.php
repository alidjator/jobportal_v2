<?php

namespace Src\Identity\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the Identity context, registered in the shared catalog by its service provider. */
final class IdentityPermissions
{
    public const string GROUP = 'identity';

    public const string USERS_VIEW = 'users.view';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [
            new PermissionDefinition(self::USERS_VIEW, self::GROUP),
        ];
    }
}
