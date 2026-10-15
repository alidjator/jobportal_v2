<?php

namespace Src\Organization\Domain;

use Src\Shared\Application\Organization\GroupPermission;

/** The group role of one account: the group and the permissions across its tenants. The account keeps its company membership; this sits on top of it. */
final readonly class GroupMember
{
    /** @param  list<GroupPermission>  $permissions */
    public function __construct(
        public ?int $id,
        public int $groupId,
        public int $userId,
        public array $permissions,
    ) {}

    public function can(GroupPermission $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
