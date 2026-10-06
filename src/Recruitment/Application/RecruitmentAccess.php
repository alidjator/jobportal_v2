<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Job\JobSummary;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The checks every recruitment action starts with: the caller is a member of an active company, and for changes a company admin. */
final readonly class RecruitmentAccess
{
    public function __construct(private CompanyDirectory $companies, private JobDirectory $jobs) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function member(Actor $actor): RecruitmentMember
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        if (! $this->companies->isActive($actor->membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return new RecruitmentMember($actor->id, $actor->membership->companyId, $actor->isCompanyAdmin());
    }

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required`
     */
    public function companyAdmin(Actor $actor): RecruitmentMember
    {
        $member = $this->member($actor);

        if (! $member->isCompanyAdmin) {
            throw new AccessDenied('Only a company admin can do this.', 'company-admin-required');
        }

        return $member;
    }

    /**
     * The job, when the member may see it: a company admin sees every job of the company, anyone else only the jobs assigned to them.
     *
     * @throws EntityNotFound when the job does not exist, belongs to another company, or the member may not see it
     */
    public function job(RecruitmentMember $member, int $jobId): JobSummary
    {
        return $this->jobs->visibleToMember($jobId, $member->companyId, $member->userId, $member->isCompanyAdmin) ?? throw EntityNotFound::of('Job', $jobId);
    }
}
