<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\EmailInvitationRepository;
use Src\JobInvitation\Domain\EmailInvitationStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The invitations by email sent for a job the member may see, newest first: the "Menunggu kandidat" list. */
final readonly class ListEmailInvitations
{
    public function __construct(private EmailInvitationAccess $access, private EmailInvitationRepository $invitations, private EmailInvitationViews $views) {}

    /**
     * @return Page<EmailInvitationView>
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required`, `company-suspended` or `feature-disabled`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $jobId, ?EmailInvitationStatus $status, PageRequest $request): Page
    {
        $this->access->member($actor, CompanyPermission::ApplicationsView, $jobId);

        return $this->views->forCompany($this->invitations->ofJob($jobId, $status, $request));
    }
}
