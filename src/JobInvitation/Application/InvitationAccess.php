<?php

namespace Src\JobInvitation\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The checks every invitation action starts with: a company side action is a member of a company that is not suspended who holds `candidates.search`; a candidate side action a signed-in job seeker. */
final readonly class InvitationAccess
{
    public function __construct(private CompanyDirectory $companies) {}

    /** @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended` */
    public function member(Actor $actor): InvitingMember
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        $actor->membership->require(CompanyPermission::CandidatesSearch);

        if (! $this->companies->isActive($actor->membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return new InvitingMember($actor->id, $actor->membership->companyId, $actor->can(CompanyPermission::JobsViewAll));
    }

    /**
     * @return int the id of the job seeker's account
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function jobSeeker(Actor $actor): int
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker has job invitations.', 'job-seeker-only');
        }

        return $actor->id;
    }
}
