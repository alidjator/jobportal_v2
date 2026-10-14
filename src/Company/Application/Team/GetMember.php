<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\MemberPreset;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A member of the actor's company in detail, for a company admin. */
final readonly class GetMember
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyMemberRepository $members,
        private CompanyMemberAccounts $accounts,
        private JobDirectory $jobs,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the account is not a member of the actor's company
     */
    public function execute(Actor $actor, int $userId): MemberDetail
    {
        $companyId = (int) $this->guard->company($actor)->id;

        $member = $this->members->find($companyId, $userId) ?? throw EntityNotFound::of('Member', $userId);
        $accounts = $this->accounts->summaries(array_values(array_filter([$userId, $member->suspendedByUserId])));
        $account = $accounts[$userId] ?? throw EntityNotFound::of('Member', $userId);

        return new MemberDetail(
            $userId, $account->name, $account->email, $member->role, $member->isOwner,
            MemberPreset::matching($member->role, $member->permissions)?->value, $member->effectivePermissions(), $member->status->value,
            $member->suspendedAt, $member->suspendedByUserId === null ? null : ($accounts[$member->suspendedByUserId] ?? null)?->name,
            $member->joinedAt, $account->lastLoginAt, count($this->jobs->assignedJobIds($companyId, $userId)),
        );
    }
}
