<?php

namespace Src\ReferenceData\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the ReferenceData context, registered in the shared catalog by its service provider. */
final class ReferenceDataPermissions
{
    public const string GROUP = 'reference_data';

    public const string MANAGE = 'reference_data.manage';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [new PermissionDefinition(self::MANAGE, self::GROUP)];
    }
}
