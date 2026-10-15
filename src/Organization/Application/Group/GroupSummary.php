<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\Group;

/** One row of the group list of the platform admin. */
final readonly class GroupSummary
{
    public function __construct(public Group $group, public int $legalEntityCount, public int $memberCount) {}
}
