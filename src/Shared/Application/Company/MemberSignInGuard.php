<?php

namespace Src\Shared\Application\Company;

use Src\Shared\Domain\Exceptions\AccessDenied;

/** Lets Identity ask Company whether an account may sign in as a company member. Provided by Company. */
interface MemberSignInGuard
{
    /**
     * @throws AccessDenied `membership-suspended` when a company admin suspended the account's membership; an account without a membership passes
     */
    public function assertMayPass(int $userId): void;
}
