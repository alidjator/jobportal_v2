<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMember;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The people a member may put on a job: the active members of the company with their name and role, for the member who holds `jobs.assign` (the picker of the job team). It has no
 * email address and no permissions: those stay with the team list a company admin reads. A suspended member is left out.
 */
final readonly class ListMemberOptions
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyMemberRepository $members,
        private CompanyMemberAccounts $accounts,
    ) {}

    /**
     * @return list<array{id: int, name: string, role: string}> by name
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     */
    public function execute(Actor $actor): array
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::JobsAssign);
        $members = array_values(array_filter($this->members->ofCompany((int) $company->id), static fn (CompanyMember $member): bool => ! $member->isSuspended()));
        $accounts = $this->accounts->summaries(array_map(static fn (CompanyMember $member): int => $member->userId, $members));
        $options = [];

        foreach ($members as $member) {
            if (isset($accounts[$member->userId])) {
                $options[] = ['id' => $member->userId, 'name' => $accounts[$member->userId]->name, 'role' => $member->role->value];
            }
        }

        usort($options, static fn (array $a, array $b): int => [mb_strtolower($a['name']), $a['id']] <=> [mb_strtolower($b['name']), $b['id']]);

        return $options;
    }
}
