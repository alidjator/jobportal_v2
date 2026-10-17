<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\InvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Recruitment\TeamApplications;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** One test invitation, for the members who may see the application it belongs to. */
final readonly class GetInvitation
{
    public function __construct(private TeamApplications $applications, private InvitationRepository $invitations, private InvitationViews $views) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $invitationId): InvitationView
    {
        $invitation = $this->invitations->find($invitationId) ?? throw EntityNotFound::of('Invitation', $invitationId);
        $this->applications->find($actor, $invitation->applicationId, permission: CompanyPermission::AssessmentsSend, panelOpens: true);

        return $this->views->one($invitation);
    }
}
