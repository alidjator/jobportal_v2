<?php

namespace Src\Shared\Application\Accounts;

/** Which platform admins exist and what they may do, for the contexts that name an admin (support access of a group). Provided by Identity. */
interface AdminAccounts
{
    /** @return list<AdminSummary> the active admins that hold the permission (directly or through a role), by name */
    public function holders(string $permission): array;

    public function holds(int $adminId, string $permission): bool;

    /**
     * @param  list<int>  $adminIds
     * @return array<int, AdminSummary> keyed by admin id; an id without an account is missing
     */
    public function summaries(array $adminIds): array;
}
