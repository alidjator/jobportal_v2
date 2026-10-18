<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the Messaging context, registered in the shared catalog by its service provider. */
final class MessagingPermissions
{
    public const string GROUP = 'messaging';

    public const string MESSAGES_MODERATE = 'messages.moderate';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [
            new PermissionDefinition(self::MESSAGES_MODERATE, self::GROUP),
        ];
    }
}
