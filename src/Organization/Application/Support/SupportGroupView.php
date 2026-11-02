<?php

namespace Src\Organization\Application\Support;

use Src\Organization\Domain\Group;
use Src\Organization\Domain\SupportGrant;

/** A group that gave the platform admin support access, with the grant that is still running. */
final readonly class SupportGroupView
{
    public function __construct(public Group $group, public SupportGrant $grant) {}
}
