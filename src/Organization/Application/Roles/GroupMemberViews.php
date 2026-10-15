<?php

namespace Src\Organization\Application\Roles;

use Src\Organization\Domain\GroupMember;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyMembershipResolver;

final readonly class GroupMemberViews
{
    public function __construct(
        private CompanyMemberAccounts $accounts,
        private CompanyMembershipResolver $memberships,
        private CompanyDirectory $companies,
    ) {}

    /**
     * @param  list<GroupMember>  $members
     * @return list<GroupMemberView>
     */
    public function of(array $members): array
    {
        $names = $this->accounts->summaries(array_map(static fn (GroupMember $member): int => $member->userId, $members));
        $companyIds = [];

        foreach ($members as $member) {
            $companyIds[$member->userId] = $this->memberships->forUser($member->userId)?->companyId;
        }

        $companies = $this->companies->summaries(array_values(array_unique(array_filter($companyIds))));

        return array_map(static function (GroupMember $member) use ($names, $companyIds, $companies): GroupMemberView {
            $companyId = $companyIds[$member->userId];

            return new GroupMemberView($member, ($names[$member->userId] ?? null)?->name, $companyId, $companyId === null ? null : ($companies[$companyId] ?? null)?->name);
        }, $members);
    }
}
