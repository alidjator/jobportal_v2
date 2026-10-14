<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMember;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company keeps at least one active company admin: a suspended one does not count. Call it inside the transaction that holds the company lock, before a
 * removal, a demotion or a suspension; the admin rows are locked until the transaction ends.
 */
final readonly class LastCompanyAdminRule
{
    public function __construct(private CompanyMemberRepository $members) {}

    /** @throws StateConflict `last-company-admin` when the member is the only active company admin */
    public function assertAnotherRemains(int $companyId, CompanyMember $member): void
    {
        if (! $member->isCompanyAdmin() || $member->isSuspended()) {
            return;
        }

        if (array_diff($this->members->lockCompanyAdminIds($companyId), [$member->userId]) === []) {
            throw new StateConflict('A company needs at least one company admin.', 'last-company-admin');
        }
    }

    /** Whether the member could leave the active admins without breaking the rule, without locking anything (for the row actions of the list). */
    public function wouldBreak(CompanyMember $member, int $activeAdminCount): bool
    {
        return $member->isCompanyAdmin() && ! $member->isSuspended() && $activeAdminCount <= 1;
    }
}
