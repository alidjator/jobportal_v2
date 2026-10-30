<?php

namespace Src\Organization\Application\Roles;

/** An account that can be given a group role: an active member of a tenant of the group, with the tenant it belongs to. */
final readonly class GroupMemberCandidate
{
    public function __construct(
        public int $userId,
        public string $name,
        public int $companyId,
        public ?string $companyName,
    ) {}
}
