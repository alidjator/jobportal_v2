<?php

namespace Src\Company\Application\Queries;

use Src\Company\Domain\ApplicationGroupRepository;
use Src\Company\Domain\CompanyRepository;

final class ListApplicationGroups
{
    public function __construct(private CompanyRepository $companies, private ApplicationGroupRepository $groups) {}

    /** @return list<array<string,mixed>> */
    public function __invoke(int $userId): array
    {
        return $this->groups->forCompany($this->companies->idForUser($userId));
    }
}
