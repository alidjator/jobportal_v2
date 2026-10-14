<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Job\JobSummary;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The checks every recruitment action starts with: the caller is a member of an active company and holds the permission of the action. Reading jobs and
 * applicants needs `applications.view` (`viewer`); the libraries (stages, screening questions, interview kits) have their own permissions (`permitted`,
 * `anyOf`).
 */
final readonly class RecruitmentAccess
{
    public function __construct(private CompanyDirectory $companies, private JobDirectory $jobs) {}

    /**
     * A member of an active company, whatever they may do there.
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     */
    public function member(Actor $actor): RecruitmentMember
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        if (! $this->companies->isActive($actor->membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return new RecruitmentMember($actor->id, $actor->membership->companyId, $actor->membership);
    }

    /**
     * A member who may read the jobs and applicants they are assigned to, and holds `$permission` when the action needs more than reading.
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     */
    public function viewer(Actor $actor, ?CompanyPermission $permission = null): RecruitmentMember
    {
        $member = $this->member($actor);
        $member->membership->require(CompanyPermission::ApplicationsView);

        if ($permission !== null) {
            $member->membership->require($permission);
        }

        return $member;
    }

    /**
     * A member who holds `$permission`.
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     */
    public function permitted(Actor $actor, CompanyPermission $permission): RecruitmentMember
    {
        $member = $this->member($actor);
        $member->membership->require($permission);

        return $member;
    }

    /**
     * A member who holds at least one of the permissions; when none is held, the error names the first.
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     */
    public function anyOf(Actor $actor, CompanyPermission ...$permissions): RecruitmentMember
    {
        $member = $this->member($actor);

        foreach ($permissions as $permission) {
            if ($member->membership->can($permission)) {
                return $member;
            }
        }

        $member->membership->require($permissions[0]);

        return $member;
    }

    /**
     * The job, when the member may see it: a member with `jobs.view_all` sees every job of the company, anyone else only the jobs assigned to them.
     *
     * @throws EntityNotFound when the job does not exist, belongs to another company, or the member may not see it
     */
    public function job(RecruitmentMember $member, int $jobId): JobSummary
    {
        return $this->jobs->visibleToMember($jobId, $member->companyId, $member->userId, $member->viewsAll()) ?? throw EntityNotFound::of('Job', $jobId);
    }
}
