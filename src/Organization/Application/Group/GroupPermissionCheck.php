<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\GroupMember;
use Src\Organization\Domain\GroupMemberRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Application\Organization\GroupPermissions;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** Organization's side of `GroupPermissions`: the group role check of `GroupAccess`, answered to other contexts as the group id. */
final readonly class GroupPermissionCheck implements GroupPermissions
{
    public function __construct(private GroupAccess $access, private GroupMemberRepository $members) {}

    public function holders(int $groupId, string $permission): array
    {
        $wanted = GroupPermission::tryFrom($permission);

        if ($wanted === null) {
            return [];
        }

        return array_values(array_map(
            static fn (GroupMember $member): int => $member->userId,
            array_filter($this->members->ofGroup($groupId), static fn (GroupMember $member): bool => $member->can($wanted)),
        ));
    }

    public function groupOf(Actor $actor, ?GroupPermission $permission = null): int
    {
        return (int) $this->access->context($actor, $permission)->group->id;
    }

    public function heldBy(Actor $actor): array
    {
        try {
            $context = $this->access->context($actor);
        } catch (AccessDenied) {
            return [];
        }

        return [
            'group_id' => (int) $context->group->id,
            'permissions' => array_map(static fn (GroupPermission $permission): string => $permission->value, $context->member?->permissions ?? []),
        ];
    }
}
