<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\Group;

/** A group with its legal entities (and their tenants) and the number of group roles; for the platform admin and for a group member. */
final readonly class GroupView
{
    /**
     * @param  list<LegalEntityView>  $legalEntities
     * @param  list<string>|null  $myPermissions  the permissions of the caller, when a group member asks
     */
    public function __construct(
        public Group $group,
        public array $legalEntities,
        public int $memberCount,
        public ?array $myPermissions = null,
    ) {}
}
