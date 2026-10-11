<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\InvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Recruitment\TeamApplications;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Every test sent for an application, newest first, for the members who may see its job. */
final readonly class ListApplicationInvitations
{
    public function __construct(private TeamApplications $applications, private InvitationRepository $invitations, private InvitationViews $views) {}

    /**
     * @return list<InvitationView>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $applicationId): array
    {
        $this->applications->find($actor, $applicationId);

        return $this->views->many($this->invitations->ofApplication($applicationId));
    }
}
