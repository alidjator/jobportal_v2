<?php

namespace Src\Shared\Application\Company;

/** Tells which company an account belongs to. Provided by Company and read when an `Actor` is built for a company member. */
interface CompanyMembershipResolver
{
    public function forUser(int $userId): ?CompanyMembership;
}
