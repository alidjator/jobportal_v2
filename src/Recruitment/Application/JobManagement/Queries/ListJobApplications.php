<?php

namespace Src\Recruitment\Application\JobManagement\Queries;

use Src\Recruitment\Application\JobManagement\Ports\CompanyDirectory;
use Src\Recruitment\Application\JobManagement\Ports\JobApplicationRepository;
use Src\Recruitment\Application\JobManagement\ResolvesCompany;
use Src\Recruitment\Domain\JobManagement\JobMessages;
use Src\Recruitment\Domain\JobManagement\JobRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class ListJobApplications
{
    use ResolvesCompany;

    public function __construct(
        CompanyDirectory $companies,
        private readonly JobRepository $jobs,
        private readonly JobApplicationRepository $applications,
    ) {
        $this->companies = $companies;
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(int $userId, int $jobId): array
    {
        $companyId = $this->companyId($userId);

        if (! $this->jobs->ownsJob($companyId, $jobId)) {
            throw new EntityNotFound(JobMessages::get('job_not_found'));
        }

        return $this->applications->groupsWithApplications($companyId, $jobId);
    }
}
