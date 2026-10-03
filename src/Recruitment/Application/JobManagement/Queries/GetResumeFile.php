<?php

namespace Src\Recruitment\Application\JobManagement\Queries;

use Src\Recruitment\Application\JobManagement\Ports\CompanyDirectory;
use Src\Recruitment\Application\JobManagement\Ports\JobApplicationRepository;
use Src\Recruitment\Application\JobManagement\ResolvesCompany;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Path file resume kandidat yang melamar ke lowongan perusahaan ini. */
final class GetResumeFile
{
    use ResolvesCompany;

    public function __construct(CompanyDirectory $companies, private readonly JobApplicationRepository $applications)
    {
        $this->companies = $companies;
    }

    public function __invoke(int $userId, int $resumeId): string
    {
        return $this->applications->resumeFileForCompany($this->companyId($userId), $resumeId)
            ?? throw EntityNotFound::of('Resume', $resumeId);
    }
}
