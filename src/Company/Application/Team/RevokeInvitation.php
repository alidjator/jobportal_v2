<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\Events\CompanyInvitationRevoked;
use Src\Company\Domain\InvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A company admin withdraws an invitation that has not been accepted yet. */
final readonly class RevokeInvitation
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private InvitationRepository $invitations,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the company has no pending invitation with that id (an invitation of another company looks the same)
     */
    public function execute(Actor $actor, int $invitationId, ?string $ipAddress, ?string $userAgent): void
    {
        $company = $this->guard->company($actor);
        $now = $this->clock->now();

        // The row is locked, so a revocation and an acceptance at the same moment cannot overwrite each other.
        $this->transactions->run(function () use ($company, $invitationId, $now): void {
            $invitation = $this->invitations->findOfCompany((int) $company->id, $invitationId, forUpdate: true);

            if ($invitation === null || ! $invitation->isPending($now)) {
                throw EntityNotFound::of('Invitation', $invitationId);
            }

            $invitation->revoke($now);
            $this->invitations->save($invitation);
        });

        $this->events->dispatch([new CompanyInvitationRevoked((int) $company->id, $invitationId, (int) $actor->id, $ipAddress, $userAgent, $now)]);
    }
}
