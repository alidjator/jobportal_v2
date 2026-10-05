<?php

namespace Src\Company\Domain;

use DateTimeImmutable;

/** A member of a company: the account (`userId`), the role and when they joined. */
final readonly class CompanyMember
{
    public function __construct(
        public int $userId,
        public CompanyRole $role,
        public DateTimeImmutable $joinedAt,
    ) {}
}
