<?php

namespace Src\Organization\Application\Admin;

use Src\Organization\Application\Group\GroupView;
use Src\Organization\Application\Group\LegalEntityViews;
use Src\Organization\Domain\GroupMemberRepository;
use Src\Organization\Domain\GroupRepository;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class GetGroup
{
    public function __construct(
        private GroupRepository $groups,
        private LegalEntityRepository $entities,
        private LegalEntityViews $views,
        private GroupMemberRepository $members,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $groupId): GroupView
    {
        AdminGuard::assert($actor, 'read a group');

        $group = $this->groups->find($groupId) ?? throw EntityNotFound::of('Group', $groupId);

        return new GroupView($group, $this->views->of($this->entities->ofGroup($groupId)), count($this->members->ofGroup($groupId)));
    }
}
