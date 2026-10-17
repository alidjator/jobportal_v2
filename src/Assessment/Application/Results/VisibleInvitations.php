<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
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
    public function find(Actor $actor, int $invitationId, bool $forUpdate = false, bool $panelOpens = false): Invitation
    {
        $invitation = $this->invitations->find($invitationId, $forUpdate) ?? throw EntityNotFound::of('Invitation', $invitationId);
        $this->applications->find($actor, $invitation->applicationId, panelOpens: $panelOpens);

        return $invitation;
    }

    /** Whether the member reaches the application only through an interview panel (the reduced view). */
    public function panelOnly(Actor $actor, Invitation $invitation): bool
    {
        return $this->applications->find($actor, $invitation->applicationId, panelOpens: true)->panelOnly;
    }

    /**
     * Like `find`, for reading results: also needs `assessments.send`, unless the member is an assessor of this invitation (assessors read what they score)
     * or sits on an interview panel of the application, which opens the results of the skill tests and not the psychological ones.
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function findForResults(Actor $actor, int $invitationId, bool $panelOpens = false): Invitation
    {
        $invitation = $this->find($actor, $invitationId, panelOpens: $panelOpens);

        if ($panelOpens && $this->panelOnly($actor, $invitation)) {
            return $invitation;
        }

        if ($actor->id === null || ! in_array($actor->id, $invitation->assessorIds, true)) {
            $actor->membership?->require(CompanyPermission::AssessmentsSend);
        }

        return $invitation;
    }
}
