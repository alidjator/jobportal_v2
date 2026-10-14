<?php

namespace Src\Shared\Application\Company;

use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Lets Identity ask Company whether an account may start deleting itself, without reading Company's tables. Provided by Company. Call it inside the
 * transaction that records the request: the check takes the company's locks, so two admins cannot both delete themselves at the same time.
 */
interface CompanyMemberDeletionGuard
{
    /**
     * Passes for an account that belongs to no company, for a member and for a company admin who is not the owner and has another active company admin.
     *
     * @throws StateConflict `last-company-admin` when the account is the only company admin that can still sign in, or `member-is-owner` when it is the owner of the company
     */
    public function assertCanRequestDeletion(int $userId): void;
}
