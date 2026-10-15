<?php

namespace Src\Organization\Application\Roles;

use Src\Organization\Domain\GroupMember;

/** A group role with who holds it and the tenant they belong to. */
final readonly class GroupMemberView
{
    public function __construct(
        public GroupMember $member,
        public ?string $name,
        public ?int $companyId,
        public ?string $companyName,
    ) {}
}
