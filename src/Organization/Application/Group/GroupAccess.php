<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\GroupMemberRepository;
use Src\Organization\Domain\GroupRepository;
use Src\Organization\Domain\SupportGrantRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * Who may act for a group: a company member who holds a group role, in a group that is not suspended, with the permission the action needs. The role is read on
 * every request, like company permissions. A platform admin acting under a group's support access (`Actor::supportGroupId`) reads as that group while its grant is active
 * and holds no permission but the audit view: every change is refused.
 */
final readonly class GroupAccess
{
    /** The only group permission support access carries: reading the group activity log (the structure is open to any group reader). */
    private const array SUPPORT_PERMISSIONS = [GroupPermission::AuditView];

    public function __construct(
        private GroupMemberRepository $members,
        private GroupRepository $groups,
        private SupportGrantRepository $grants,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended`, `group-permission-required` or, under support access, `support-access-required`
     */
    public function context(Actor $actor, ?GroupPermission $permission = null): GroupContext
    {
        if ($actor->kind === ActorKind::Admin && $actor->supportGroupId !== null) {
            return $this->support($actor, $actor->supportGroupId, $permission);
        }

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

    /** @throws AccessDenied `support-access-required` or `group-permission-required` */
    private function support(Actor $actor, int $groupId, ?GroupPermission $permission): GroupContext
    {
        $group = $this->groups->find($groupId);

        if ($group === null || $actor->id === null || $this->grants->activeFor($groupId, $actor->id, $this->clock->now()) === null) {
            throw new AccessDenied('This group has not granted you support access, or the access has ended.', 'support-access-required');
        }

        if ($permission !== null && ! in_array($permission, self::SUPPORT_PERMISSIONS, true)) {
            throw new AccessDenied('Support access is read only.', 'group-permission-required');
        }

        return new GroupContext($group, null);
    }
}
