<?php

namespace Src\JobSeeker\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the JobSeeker context, registered in the shared catalog by its service provider. */
final class JobSeekerPermissions
{
    public const string GROUP = 'job_seeker';

    public const string CV_TEMPLATES_MANAGE = 'cv_templates.manage';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [
            new PermissionDefinition(self::CV_TEMPLATES_MANAGE, self::GROUP),
        ];
    }
}
