<?php

namespace Src\Organization\Application\Admin;

use Src\Organization\Application\Group\GroupSummary;
use Src\Organization\Domain\Group;
use Src\Organization\Domain\GroupMemberRepository;
use Src\Organization\Domain\GroupRepository;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The groups for the platform admin, by name, with how many legal entities and group roles each holds. */
final readonly class ListGroups
{
    public function __construct(private GroupRepository $groups, private LegalEntityRepository $entities, private GroupMemberRepository $members) {}

    /**
     * @return Page<GroupSummary>
     *
     * @throws AccessDenied when the actor is not an admin
     */
    public function execute(Actor $actor, PageRequest $request): Page
    {
        AdminGuard::assert($actor, 'list groups');

        $page = $this->groups->page($request);
        $ids = array_map(static fn (Group $group): int => (int) $group->id, $page->items);
        $entities = $this->entities->countByGroup($ids);
        $members = $this->members->countByGroup($ids);

        return new Page(
            array_map(static fn (Group $group): GroupSummary => new GroupSummary($group, $entities[(int) $group->id] ?? 0, $members[(int) $group->id] ?? 0), $page->items),
            $page->total,
            $request,
        );
    }
}
