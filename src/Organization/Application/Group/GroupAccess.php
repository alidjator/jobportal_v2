<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\GroupMemberRepository;
use Src\Organization\Domain\GroupRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * Who may act for a group: a company member who holds a group role, in a group that is not suspended, with the permission the action needs. The role is read on
 * every request, like company permissions.
 */
final readonly class GroupAccess
{
    public function __construct(private GroupMemberRepository $members, private GroupRepository $groups) {}

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     */
    public function context(Actor $actor, ?GroupPermission $permission = null): GroupContext
    {
        $member = $actor->kind === ActorKind::CompanyMember && $actor->id !== null ? $this->members->findByUser($actor->id) : null;
        $group = $member === null ? null : $this->groups->find($member->groupId);

        if ($member === null || $group === null) {
            throw new AccessDenied('This account holds no group role.', 'not-a-group-member');
        }

        if (! $group->isActive()) {
            throw new AccessDenied('The group is suspended.', 'group-suspended');
        }

        if ($permission !== null && ! $member->can($permission)) {
            throw new AccessDenied("The group permission {$permission->value} is needed.", 'group-permission-required');
        }

        return new GroupContext($group, $member);
    }
}
