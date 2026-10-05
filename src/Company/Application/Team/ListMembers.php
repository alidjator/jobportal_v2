<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMember;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRole;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The members of the actor's company, company admins first and then by name. Not paginated: a team is small. */
final readonly class ListMembers
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyMemberRepository $members,
        private CompanyMemberAccounts $accounts,
    ) {}

    /**
     * @return list<MemberView>
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     */
    public function execute(Actor $actor): array
    {
        $company = $this->guard->company($actor);
        $members = $this->members->ofCompany((int) $company->id);
        $accounts = $this->accounts->summaries(array_map(fn (CompanyMember $m): int => $m->userId, $members));

        $views = [];

        foreach ($members as $member) {
            $account = $accounts[$member->userId] ?? null;

            if ($account !== null) {
                $views[] = new MemberView($member->userId, $account->name, $account->email, $member->role, $account->status, $member->joinedAt, $account->lastLoginAt);
            }
        }

        usort($views, fn (MemberView $a, MemberView $b): int => [$a->role === CompanyRole::CompanyAdmin ? 0 : 1, mb_strtolower($a->name), $a->userId]
            <=> [$b->role === CompanyRole::CompanyAdmin ? 0 : 1, mb_strtolower($b->name), $b->userId]);

        return $views;
    }
}
