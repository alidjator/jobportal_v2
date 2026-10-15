<?php

namespace Src\Organization\Application\Roles;

use Src\Organization\Domain\GroupMemberRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class ListGroupMembers
{
    public function __construct(private GroupRoleAccess $access, private GroupMemberRepository $members, private GroupMemberViews $views) {}

    /**
     * @return list<GroupMemberView>
     *
     * @throws AccessDenied
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, ?int $groupId): array
    {
        return $this->views->of($this->members->ofGroup($this->access->groupId($actor, $groupId)));
    }
}
