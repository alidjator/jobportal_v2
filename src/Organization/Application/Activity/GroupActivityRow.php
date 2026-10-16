<?php

namespace Src\Organization\Application\Activity;

use Src\Shared\Application\Audit\ActivityEntry;

/** One entry of the group activity log with the name of its actor (null for a platform admin or the system) and the name of the tenant it belongs to (null for none). */
final readonly class GroupActivityRow
{
    public function __construct(public ActivityEntry $entry, public ?string $actorName, public ?string $companyName) {}
}
