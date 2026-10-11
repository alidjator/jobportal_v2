<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Recruitment\TeamApplications;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** An invitation for the company members who may see the application it belongs to; anyone else gets the same 404 as for a missing one. */
final readonly class VisibleInvitations
{
    public function __construct(private TeamApplications $applications, private InvitationRepository $invitations) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function find(Actor $actor, int $invitationId, bool $forUpdate = false): Invitation
    {
        $invitation = $this->invitations->find($invitationId, $forUpdate) ?? throw EntityNotFound::of('Invitation', $invitationId);
        $this->applications->find($actor, $invitation->applicationId);

        return $invitation;
    }
}
