<?php

namespace Src\Company\Application\Team;

use DateTimeImmutable;
use Src\Company\Domain\CompanyRole;

/** One member of the company with everything the detail page shows. `preset` names the preset the permissions match, or null for a custom set. */
final readonly class MemberDetail
{
    /** @param  list<string>  $permissions  every permission for a company admin */
    public function __construct(
        public int $userId,
        public string $name,
        public string $email,
        public CompanyRole $role,
        public bool $isOwner,
        public ?string $preset,
        public array $permissions,
        public string $status,
        public ?DateTimeImmutable $suspendedAt,
        public ?string $suspendedBy,
        public DateTimeImmutable $joinedAt,
        public ?DateTimeImmutable $lastLoginAt,
        public int $assignedJobCount,
        public string $twoFactor,
    ) {}
}
