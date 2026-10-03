<?php

namespace Src\Recruitment\Application\JobManagement\Queries;

use Src\Recruitment\Application\JobManagement\Ports\CompanyDirectory;
use Src\Recruitment\Application\JobManagement\ResolvesCompany;
use Src\Recruitment\Domain\JobManagement\JobRepository;

final class ListCompanyJobs
{
    use ResolvesCompany;

    public const PER_PAGE = 5;

    public function __construct(CompanyDirectory $companies, private readonly JobRepository $jobs)
    {
        $this->companies = $companies;
    }

    /** @return array<string, mixed> */
    public function __invoke(int $userId, ?string $status): array
    {
        return $this->jobs->paginateForCompany($this->companyId($userId), $status ?: 'active', self::PER_PAGE);
    }
}
