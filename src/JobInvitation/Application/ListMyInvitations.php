<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\InvitationRepository;
use Src\JobInvitation\Domain\InvitationStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The invitations of the signed-in job seeker, newest first. */
final readonly class ListMyInvitations
{
    public function __construct(private InvitationAccess $access, private InvitationRepository $invitations, private InvitationViews $views) {}

    /**
     * @return Page<InvitationView>
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor, ?InvitationStatus $status, PageRequest $request): Page
    {
        return $this->views->forCandidate($this->invitations->ofUser($this->access->jobSeeker($actor), $status, $request));
    }
}
