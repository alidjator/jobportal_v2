<?php

namespace Src\Job\Application;

use Src\Job\Domain\Job;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The checks every company-side job action starts with: the actor is a member of an active company, and for one job, that the job belongs to the
 * company and the member may see it. A company admin sees every job of the company, a recruiter only the ones assigned to them; for anyone else the
 * job simply does not exist (404), so a recruiter cannot learn that a job they are not assigned to is there.
 */
final readonly class JobAccess
{
    public function __construct(
        private CompanyDirectory $companies,
        private JobRepository $jobs,
        private JobAssignmentRepository $assignments,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function member(Actor $actor): JobMember
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can manage jobs.', 'not-a-company-member');
        }

        if (! $this->companies->isActive($actor->membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return new JobMember($actor->id, $actor->membership->companyId, $actor->isCompanyAdmin());
    }

    /**
     * @throws EntityNotFound when the job does not exist, belongs to another company, or the member may not see it
     */
    public function job(JobMember $member, int $jobId): Job
    {
        $job = $this->jobs->find($jobId);

        if ($job === null || $job->companyId !== $member->companyId) {
            throw EntityNotFound::of('Job', $jobId);
        }

        if (! $member->isCompanyAdmin && ! in_array($member->userId, $this->assignments->userIds($jobId), true)) {
            throw EntityNotFound::of('Job', $jobId);
        }

        return $job;
    }

    /**
     * @throws AccessDenied `company-admin-required`
     */
    public function requireCompanyAdmin(JobMember $member): void
    {
        if (! $member->isCompanyAdmin) {
            throw new AccessDenied('Only a company admin can do this.', 'company-admin-required');
        }
    }
}
