<?php

namespace Src\Shared\Application\Company;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Lets Identity apply the optional email-domain limit of a company to a member who changes their own sign-in email. Provided by Company; it passes for an account that belongs to no company. */
interface MemberEmailDomains
{
    /** @throws BusinessRuleViolation `email-domain-not-allowed` when the member's company limits its addresses to other domains */
    public function assertAllowed(int $userId, string $email): void;
}
