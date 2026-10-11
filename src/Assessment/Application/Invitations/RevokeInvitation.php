<?php

namespace Src\Assessment\Application\Invitations;

use Src\Assessment\Application\Admin\AdminOnly;
use Src\Assessment\Domain\PsychologistInvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A platform admin revokes a pending invitation. Revoking one that is not pending changes nothing. */
final readonly class RevokeInvitation
{
    public function __construct(
        private PsychologistInvitationRepository $invitations,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the provider has no such invitation
     */
    public function execute(Actor $actor, int $providerId, int $invitationId): void
    {
        AdminOnly::assert($actor, 'revoke an invitation');

        $this->transactions->run(function () use ($providerId, $invitationId): void {
            $invitation = $this->invitations->findOfProvider($providerId, $invitationId, forUpdate: true)
                ?? throw EntityNotFound::of('Invitation', $invitationId);

            $invitation->revoke($this->clock->now());
            $this->invitations->save($invitation);
        });
    }
}
