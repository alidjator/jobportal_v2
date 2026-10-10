<?php

namespace Src\Review\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permission of the Review context, registered in the shared catalog by its service provider. */
final class ReviewPermissions
{
    public const string GROUP = 'reviews';

    public const string MODERATE = 'reviews.moderate';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [new PermissionDefinition(self::MODERATE, self::GROUP)];
    }
}
