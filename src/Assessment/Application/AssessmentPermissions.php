<?php

namespace Src\Assessment\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the Assessment context, registered in the shared catalog by its service provider. */
final class AssessmentPermissions
{
    public const string GROUP = 'assessment';

    public const string PROVIDERS_MANAGE = 'assessment_providers.manage';

    public const string LIBRARY_MANAGE = 'assessment_library.manage';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [
            new PermissionDefinition(self::PROVIDERS_MANAGE, self::GROUP),
            new PermissionDefinition(self::LIBRARY_MANAGE, self::GROUP),
        ];
    }
}
