<?php

namespace Src\Shared\Application\Organization;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** Lets other contexts check a group role of the actor without reading Organization's tables. Provided by Organization. */
interface GroupPermissions
{
    /**
     * The id of the group whose role the actor holds, after checking that the group is not suspended and that the role holds the permission (any role when null).
     *
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     */
    public function groupOf(Actor $actor, ?GroupPermission $permission = null): int;

    /**
     * The accounts of the group that hold the permission (empty for a permission name that does not exist).
     *
     * @return list<int>
     */
    public function holders(int $groupId, string $permission): array;

    /**
     * The group role of the actor as `group_id` and the permissions it holds; empty when the actor holds no role or the group is suspended.
     *
     * @return array{group_id: int, permissions: list<string>}|array{}
     */
    public function heldBy(Actor $actor): array;
}
