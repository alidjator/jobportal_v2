<?php

namespace Src\Campaign\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the Campaign context, registered in the shared catalog by its service provider. */
final class CampaignPermissions
{
    public const string GROUP = 'campaign';

    public const string CAMPAIGNS_MANAGE = 'campaigns.manage';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [
            new PermissionDefinition(self::CAMPAIGNS_MANAGE, self::GROUP),
        ];
    }
}
