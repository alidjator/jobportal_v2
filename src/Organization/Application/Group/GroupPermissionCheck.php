<?php

namespace Src\Organization\Application\Group;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Application\Organization\GroupPermissions;

/** Organization's side of `GroupPermissions`: the group role check of `GroupAccess`, answered to other contexts as the group id. */
final readonly class GroupPermissionCheck implements GroupPermissions
{
    public function __construct(private GroupAccess $access) {}

    public function groupOf(Actor $actor, ?GroupPermission $permission = null): int
    {
        return (int) $this->access->context($actor, $permission)->group->id;
    }
}
