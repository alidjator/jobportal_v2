<?php

namespace Src\Assessment\Application\Team;

use Src\Assessment\Domain\PsychologistInvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A lead revokes a pending invitation of their institution. Revoking one that is not pending changes nothing. */
final readonly class RevokeTeamInvitation
{
    public function __construct(
        private TeamAccess $access,
        private PsychologistInvitationRepository $invitations,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-lead` and the refusals of `TeamAccess`
     * @throws EntityNotFound when the institution has no such invitation
     */
    public function execute(Actor $actor, int $invitationId): void
    {
        $lead = $this->access->lead($actor);

        $this->transactions->run(function () use ($lead, $invitationId): void {
            $invitation = $this->invitations->findOfProvider($lead->providerId, $invitationId, forUpdate: true)
                ?? throw EntityNotFound::of('Invitation', $invitationId);

            $invitation->revoke($this->clock->now());
            $this->invitations->save($invitation);
        });
    }
}
