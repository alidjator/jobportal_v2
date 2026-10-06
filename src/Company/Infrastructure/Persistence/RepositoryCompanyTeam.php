<?php

namespace Src\Company\Infrastructure\Persistence;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Shared\Application\Company\CompanyTeam;

final readonly class RepositoryCompanyTeam implements CompanyTeam
{
    public function __construct(private CompanyMemberRepository $members) {}

    public function roles(int $companyId): array
    {
        $roles = [];

        foreach ($this->members->ofCompany($companyId) as $member) {
            $roles[$member->userId] = $member->role->value;
        }

        return $roles;
    }
}
