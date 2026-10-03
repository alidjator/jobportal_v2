<?php

namespace Src\Company\Application\Commands;

use Src\Company\Domain\ApplicationGroupRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class DeleteApplicationGroup
{
    public function __construct(private CompanyRepository $companies, private ApplicationGroupRepository $groups) {}

    public function __invoke(int $userId, int $groupId): string
    {
        $companyId = $this->companies->idForUser($userId);
        $group = $this->groups->find($companyId, $groupId) ?? throw new EntityNotFound('Group not found');

        if (! $group['is_deleteable']) {
            throw new BusinessRuleViolation('Group is not deletable');
        }

        $this->groups->deleteMovingApplications($companyId, $groupId);

        return 'Group Deleted Successfully';
    }
}
