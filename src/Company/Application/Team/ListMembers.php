<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMember;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\Invitation;
use Src\Company\Domain\InvitationRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The team of the actor's company in one list: the members and the open invitations (pending, and expired for 30 days). The owner comes first, then
 * the other company admins by name, then the members by name; an invitation sits in the group of its role. `$search` matches the name or the email,
 * case-insensitively and partially, invitations included. Each row carries the actions the caller may take now. A team is small, so the list is
 * built in memory and sliced.
 */
final readonly class ListMembers
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyMemberRepository $members,
        private InvitationRepository $invitations,
        private LastCompanyAdminRule $lastAdmin,
        private CompanyMemberAccounts $accounts,
        private MemberTwoFactorStatuses $twoFactor,
        private JobDirectory $jobs,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     */
    public function execute(Actor $actor, ?string $search, PageRequest $request): TeamList
    {
        $company = $this->guard->company($actor);
        $companyId = (int) $company->id;
        $now = $this->clock->now();

        $members = $this->members->ofCompany($companyId);
        $invitations = $this->invitations->listedOfCompany($companyId, $now);

        $inviterIds = array_values(array_unique(array_filter(array_map(fn (Invitation $i): ?int => $i->invitedByUserId, $invitations))));
        $accounts = $this->accounts->summaries([...array_map(fn (CompanyMember $m): int => $m->userId, $members), ...$inviterIds]);
        $activeAdmins = count(array_filter($members, fn (CompanyMember $m): bool => $m->isCompanyAdmin() && ! $m->isSuspended()));
        $twoFactor = $this->twoFactor->of($companyId, $members);
        $jobCounts = $this->jobs->assignedJobCounts(array_map(fn (CompanyMember $m): int => $m->userId, $members));
        $callerIsOwner = $actor->id !== null && $this->members->ownerUserId($companyId) === $actor->id;

        $rows = [];

        foreach ($members as $member) {
            $account = $accounts[$member->userId] ?? null;

            if ($account === null) {
                continue;
            }

            $rows[] = new TeamRow(
                TeamRow::TYPE_MEMBER, $member->userId, $account->name, $account->email, $member->role, $member->isOwner, $member->status->value,
                $member->effectivePermissions(), $this->memberActions($member, (int) $actor->id, $callerIsOwner, $activeAdmins), $member->joinedAt, $account->lastLoginAt, null, null, null, $twoFactor[$member->userId] ?? null, $jobCounts[$member->userId] ?? 0, $account->hasAvatar,
            );
        }

        foreach ($invitations as $invitation) {
            $expired = $invitation->isExpired($now);

            $rows[] = new TeamRow(
                TeamRow::TYPE_INVITATION, (int) $invitation->id, $invitation->name ?? '', $invitation->email, $invitation->role, false, $expired ? 'expired' : 'pending',
                $invitation->role === CompanyRole::CompanyAdmin ? CompanyPermission::values() : ($invitation->permissions ?? []),
                $expired ? ['resend'] : ['edit', 'resend', 'revoke'], null, null, $invitation->sentAt, $invitation->expiresAt,
                $invitation->invitedByUserId === null ? null : ($accounts[$invitation->invitedByUserId] ?? null)?->name,
            );
        }

        $needle = $search === null || trim($search) === '' ? null : mb_strtolower(trim($search));

        if ($needle !== null) {
            $rows = array_values(array_filter($rows, fn (TeamRow $row): bool => str_contains(mb_strtolower($row->name), $needle) || str_contains(mb_strtolower($row->email), $needle)));
        }

        usort($rows, fn (TeamRow $a, TeamRow $b): int => [$a->group(), mb_strtolower($a->name), $a->type, $a->id] <=> [$b->group(), mb_strtolower($b->name), $b->type, $b->id]);

        return new TeamList(new Page(array_slice($rows, $request->offset(), $request->perPage), count($rows), $request), count($members));
    }

    /** @return list<string> */
    private function memberActions(CompanyMember $member, int $callerId, bool $callerIsOwner, int $activeAdmins): array
    {
        $self = $member->userId === $callerId;
        $removable = ! $member->isOwner && ! $self && ! $this->lastAdmin->wouldBreak($member, $activeAdmins);

        return array_values(array_filter([
            'edit',
            $removable ? 'remove' : null,
            $removable && ! $member->isSuspended() ? 'suspend' : null,
            $member->isSuspended() ? 'reactivate' : null,
            $callerIsOwner && ! $self && $member->isCompanyAdmin() && ! $member->isSuspended() ? 'make_owner' : null,
        ]));
    }
}
