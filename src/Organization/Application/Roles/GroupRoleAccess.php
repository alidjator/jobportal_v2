<?php

namespace Src\Organization\Application\Roles;

use Src\Organization\Application\Admin\AdminGuard;
use Src\Organization\Application\Group\GroupAccess;
use Src\Organization\Domain\GroupRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Who may manage the group roles of a group: a platform admin (for the first group admin, and for support) or a group member with `group.members.manage` in
 * that very group. A group member never names another group: the group of the caller is the one.
 */
final readonly class GroupRoleAccess
{
    public function __construct(private GroupAccess $access, private GroupRepository $groups) {}

    /**
     * @return int the id of the group the roles belong to
     *
     * @throws AccessDenied
     * @throws EntityNotFound the group does not exist (a platform admin names it)
     */
    public function groupId(Actor $actor, ?int $groupId): int
    {
        if ($actor->isAdmin()) {
            AdminGuard::assert($actor, 'manage group roles');
            $id = $groupId ?? throw EntityNotFound::of('Group', 0);
            $this->groups->find($id) ?? throw EntityNotFound::of('Group', $id);

            return $id;
        }

        return (int) $this->access->context($actor, GroupPermission::MembersManage)->group->id;
    }
}
