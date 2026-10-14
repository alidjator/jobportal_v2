<?php

namespace Src\Company\Application\Team;

use DateTimeImmutable;
use Src\Company\Domain\CompanyRole;

/** One member in the company's team list. */
final readonly class MemberView
{
    /** @param  list<string>  $permissions  every permission for a company admin */
    public function __construct(
        public int $userId,
        public string $name,
        public string $email,
        public CompanyRole $role,
        public array $permissions,
        public string $status,
        public DateTimeImmutable $joinedAt,
        public ?DateTimeImmutable $lastLoginAt,
    ) {}
}
