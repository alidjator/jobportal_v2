<?php

namespace Src\Job\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the Job context, registered in the shared catalog by its service provider. */
final class JobPermissions
{
    public const string GROUP = 'job';

    public const string JOBS_MANAGE = 'jobs.manage';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [
            new PermissionDefinition(self::JOBS_MANAGE, self::GROUP),
        ];
    }
}
