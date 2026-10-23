<?php

namespace Src\Assessment\Application\Defaults;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The checks of a company member reaching the default tests of a job: the permission to send tests, an active company and a job the member may see. */
final readonly class JobDefaultAccess
{
    public function __construct(private CompanyDirectory $companies, private JobDirectory $jobs) {}

    /**
     * @return int the company of the member
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound when the member cannot see the job
     */
    public function companyId(Actor $actor, int $jobId): int
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        $companyId = $actor->membership->companyId;

        if (! $this->companies->isActive($companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        $actor->membership->require(CompanyPermission::AssessmentsSend);

        if ($this->jobs->visibleToMember($jobId, $companyId, $actor->id, $actor->membership->can(CompanyPermission::JobsViewAll)) === null) {
            throw EntityNotFound::of('Job', $jobId);
        }

        return $companyId;
    }
}
