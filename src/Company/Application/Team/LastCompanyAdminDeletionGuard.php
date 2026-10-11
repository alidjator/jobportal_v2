<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\CompanyRole;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Company\CompanyMemberDeletionGuard;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The same rule as `RemoveMember`, applied to a member who deletes their own account: a company keeps at least one company admin. An admin whose
 * account is already deactivated (a deletion pending, or removed) does not count, so two admins cannot both leave a company without one.
 */
final readonly class LastCompanyAdminDeletionGuard implements CompanyMemberDeletionGuard
{
    public function __construct(
        private CompanyRepository $companies,
        private CompanyMemberRepository $members,
        private CompanyMemberAccounts $accounts,
    ) {}

    public function assertCanRequestDeletion(int $userId): void
    {
        $membership = $this->members->findByUserId($userId);

        if ($membership === null || $membership->role !== CompanyRole::CompanyAdmin->value) {
            return;
        }

        $this->companies->lock($membership->companyId);

        $otherAdminIds = array_values(array_diff($this->members->lockCompanyAdminIds($membership->companyId), [$userId]));
        $summaries = $this->accounts->summaries($otherAdminIds);

        foreach ($otherAdminIds as $adminId) {
            if (($summaries[$adminId]->status ?? null) === 'active') {
                return;
            }
        }

        throw new StateConflict('A company needs at least one company admin.', 'last-company-admin');
    }
}
