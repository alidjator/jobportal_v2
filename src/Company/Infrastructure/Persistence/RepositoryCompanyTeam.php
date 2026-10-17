<?php

namespace Src\Company\Infrastructure\Persistence;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Shared\Application\Company\CompanyPermission;
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

    public function viewAllUserIds(int $companyId): array
    {
        $ids = [];

        foreach ($this->members->ofCompany($companyId) as $member) {
            if (in_array(CompanyPermission::JobsViewAll->value, $member->effectivePermissions(), true)) {
                $ids[] = $member->userId;
            }
        }

        return $ids;
    }

    public function interviewerUserIds(int $companyId): array
    {
        $ids = [];

        foreach ($this->members->ofCompany($companyId) as $member) {
            if (in_array(CompanyPermission::InterviewsParticipate->value, $member->effectivePermissions(), true)) {
                $ids[] = $member->userId;
            }
        }

        return $ids;
    }

    public function ownerUserId(int $companyId): ?int
    {
        return $this->members->ownerUserId($companyId);
    }
}
