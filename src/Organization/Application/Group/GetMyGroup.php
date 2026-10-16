<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\GroupMemberRepository;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The group of the caller: its legal entities with their tenants, and what the caller may do there. Any group role may read it. */
final readonly class GetMyGroup
{
    public function __construct(
        private GroupAccess $access,
        private LegalEntityRepository $entities,
        private LegalEntityViews $views,
        private GroupMemberRepository $members,
    ) {}

    /** @throws AccessDenied `not-a-group-member` or `group-suspended` */
    public function execute(Actor $actor): GroupView
    {
        $context = $this->access->context($actor);

        return new GroupView(
            $context->group,
            $this->views->of($this->entities->ofGroup((int) $context->group->id)),
            count($this->members->ofGroup((int) $context->group->id)),
            array_map(static fn (GroupPermission $permission): string => $permission->value, $context->member->permissions ?? []),
        );
    }
}
