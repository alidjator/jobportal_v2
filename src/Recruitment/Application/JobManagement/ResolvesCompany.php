<?php

namespace Src\Recruitment\Application\JobManagement;

use Src\Recruitment\Application\JobManagement\Ports\CompanyDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;

trait ResolvesCompany
{
    private CompanyDirectory $companies;

    private function companyId(int $userId): int
    {
        return $this->companies->idForUser($userId) ?? throw new AccessDenied('Company profile not found');
    }
}
