<?php

namespace Src\Organization\Application\Support;

use Src\Organization\Application\Admin\AdminGuard;
use Src\Organization\Domain\Group;
use Src\Organization\Domain\GroupRepository;
use Src\Organization\Domain\SupportGrant;
use Src\Organization\Domain\SupportGrantRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The groups that have given the platform admin support access that is still running, with the end of each grant. */
final readonly class ListMySupportGroups
{
    public function __construct(private SupportGrantRepository $grants, private GroupRepository $groups, private Clock $clock) {}

    /**
     * @return list<array{group: Group, grant: SupportGrant}>
     *
     * @throws AccessDenied the actor is not a platform admin
     */
    public function execute(Actor $actor): array
    {
        AdminGuard::assert($actor, 'list the groups that granted support access');

        $rows = [];

        foreach ($this->grants->activeOfAdmin((int) $actor->id, $this->clock->now()) as $grant) {
            $group = $this->groups->find($grant->groupId);

            if ($group !== null) {
                $rows[] = ['group' => $group, 'grant' => $grant];
            }
        }

        return $rows;
    }
}
