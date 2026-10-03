<?php

namespace Src\Company\Application\Queries;

use Src\Company\Domain\CompanyRepository;

final class GetCompanyDashboard
{
    public function __construct(private CompanyRepository $companies) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId): array
    {
        return $this->companies->dashboard($this->companies->idForUser($userId));
    }
}
