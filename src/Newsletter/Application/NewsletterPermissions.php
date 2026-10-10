<?php

namespace Src\Newsletter\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the Newsletter context, registered in the shared catalog by its service provider. */
final class NewsletterPermissions
{
    public const string GROUP = 'newsletter';

    public const string NEWSLETTER_MANAGE = 'newsletter.manage';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [
            new PermissionDefinition(self::NEWSLETTER_MANAGE, self::GROUP),
        ];
    }
}
