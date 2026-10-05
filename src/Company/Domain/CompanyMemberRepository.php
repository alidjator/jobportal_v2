<?php

namespace Src\Company\Domain;

use Src\Shared\Application\Company\CompanyMembership;

interface CompanyMemberRepository
{
    /** The company and role of the account, or null when it belongs to none. */
    public function findByUserId(int $userId): ?CompanyMembership;

    /** Makes the account a member. An account can belong to only one company; the database enforces it. */
    public function add(int $companyId, int $userId, CompanyRole $role): void;
}
