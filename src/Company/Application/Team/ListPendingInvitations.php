<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\Invitation;
use Src\Company\Domain\InvitationRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The invitations of the actor's company that can still be accepted, newest first. Not paginated: a company has few. */
final readonly class ListPendingInvitations
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private InvitationRepository $invitations,
        private CompanyMemberAccounts $accounts,
        private Clock $clock,
    ) {}

    /**
     * @return list<PendingInvitationView>
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     */
    public function execute(Actor $actor): array
    {
        $company = $this->guard->company($actor);
        $pending = $this->invitations->pendingOfCompany((int) $company->id, $this->clock->now());

        $inviterIds = array_values(array_unique(array_filter(array_map(fn (Invitation $i): ?int => $i->invitedByUserId, $pending))));
        $inviters = $this->accounts->summaries($inviterIds);

        return array_map(fn (Invitation $i): PendingInvitationView => new PendingInvitationView(
            (int) $i->id,
            $i->email,
            $i->name,
            $i->role,
            $i->role === CompanyRole::CompanyAdmin ? CompanyPermission::values() : ($i->permissions ?? []),
            $i->sentAt,
            $i->expiresAt,
            $i->invitedByUserId === null ? null : ($inviters[$i->invitedByUserId] ?? null)?->name,
        ), $pending);
    }
}
