<?php

namespace Src\Content\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the Content context, registered in the shared catalog by its service provider. */
final class ContentPermissions
{
    public const string GROUP = 'content';

    public const string LEGAL_DOCUMENTS_MANAGE = 'legal_documents.manage';

    public const string FAQS_MANAGE = 'faqs.manage';

    public const string ANNOUNCEMENTS_MANAGE = 'announcements.manage';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [
            new PermissionDefinition(self::LEGAL_DOCUMENTS_MANAGE, self::GROUP),
            new PermissionDefinition(self::FAQS_MANAGE, self::GROUP),
            new PermissionDefinition(self::ANNOUNCEMENTS_MANAGE, self::GROUP),
        ];
    }
}
