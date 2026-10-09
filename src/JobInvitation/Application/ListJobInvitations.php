<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\InvitationRepository;
use Src\JobInvitation\Domain\InvitationStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The invitations sent for a job the member may see, newest first. */
final readonly class ListJobInvitations
{
    public function __construct(private InvitationAccess $access, private JobDirectory $jobs, private InvitationRepository $invitations, private InvitationViews $views) {}

    /**
     * @return Page<InvitationView>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $jobId, ?InvitationStatus $status, PageRequest $request): Page
    {
        $member = $this->access->member($actor);
        $this->jobs->visibleToMember($jobId, $member->companyId, $member->userId, $member->isCompanyAdmin) ?? throw EntityNotFound::of('Job', $jobId);

        return $this->views->forCompany($this->invitations->ofJob($jobId, $status, $request));
    }
}
