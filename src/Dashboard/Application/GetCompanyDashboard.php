<?php

namespace Src\Dashboard\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The dashboard of a company member, kept for 2 minutes. A company admin sees every job of the company, a recruiter only the jobs assigned to them, in every card.
 */
final readonly class GetCompanyDashboard
{
    private const int CACHE_SECONDS = 120;

    public function __construct(
        private DashboardComposer $composer,
        private CompanyDirectory $companies,
        private JobDirectory $jobs,
    ) {}

    /** @throws AccessDenied `not-a-company-member` or `company-suspended` */
    public function execute(Actor $actor): DashboardView
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member has this dashboard.', 'not-a-company-member');
        }

        $companyId = $actor->membership->companyId;

        if (! $this->companies->isActive($companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        $jobIds = $actor->isCompanyAdmin() ? null : $this->jobs->assignedJobIds($companyId, $actor->id);
        $scope = new DashboardScope(DashboardAudience::Company, $actor->id, $companyId, $jobIds);
        $key = $jobIds === null ? "dashboard:company:{$companyId}:all" : "dashboard:company:{$companyId}:member:{$actor->id}";

        return $this->composer->compose($scope, $key, self::CACHE_SECONDS);
    }
}
