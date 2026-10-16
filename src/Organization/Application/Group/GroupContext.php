<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\Group;
use Src\Organization\Domain\GroupMember;

/** The group an account acts for and the role it holds there (null for a platform admin under support access), after `GroupAccess` checked both. */
final readonly class GroupContext
{
    public function __construct(public Group $group, public ?GroupMember $member) {}
}
