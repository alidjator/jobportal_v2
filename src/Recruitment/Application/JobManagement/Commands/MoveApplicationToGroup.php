<?php

namespace Src\Recruitment\Application\JobManagement\Commands;

use Src\Recruitment\Application\JobManagement\Ports\CompanyDirectory;
use Src\Recruitment\Application\JobManagement\Ports\JobApplicationRepository;
use Src\Recruitment\Application\JobManagement\ResolvesCompany;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class MoveApplicationToGroup
{
    use ResolvesCompany;

    public function __construct(
        CompanyDirectory $companies,
        private readonly JobApplicationRepository $applications,
    ) {
        $this->companies = $companies;
    }

    public function __invoke(int $userId, int $applicationId, int $groupId): void
    {
        $companyId = $this->companyId($userId);

        if (! $this->applications->applicationBelongsToCompany($companyId, $applicationId)) {
            throw EntityNotFound::of('Application', $applicationId);
        }
        if (! $this->applications->groupBelongsToCompany($companyId, $groupId)) {
            throw EntityNotFound::of('Application group', $groupId);
        }

        $this->applications->moveToGroup($applicationId, $groupId);
    }
}
