<?php

namespace Src\Shared\Application\Company;

/** Tells Identity whether an account must have a second factor as a company member. Provided by Company. */
interface MemberTwoFactorPolicy
{
    /** True for a company admin, and for every member of a company that switched `company.require_two_factor` on; false for any other account. */
    public function isRequired(int $userId): bool;
}
