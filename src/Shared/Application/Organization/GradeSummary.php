<?php

namespace Src\Shared\Application\Organization;

/** A grade of a group: its group, its rank (higher is more senior) and whether it is still in use. */
final readonly class GradeSummary
{
    public function __construct(public int $id, public int $groupId, public int $rank, public bool $active) {}
}
