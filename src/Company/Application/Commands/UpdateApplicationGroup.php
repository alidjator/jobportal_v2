<?php

namespace Src\Company\Application\Commands;

use Src\Company\Domain\ApplicationGroupRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class UpdateApplicationGroup
{
    public function __construct(private CompanyRepository $companies, private ApplicationGroupRepository $groups) {}

    /** @return array{group:array<string,mixed>,message:string} */
    public function __invoke(int $userId, int $groupId, string $name): array
    {
        $companyId = $this->companies->idForUser($userId);

        if ($this->groups->find($companyId, $groupId) === null) {
            throw new EntityNotFound('Group not found');
        }

        return ['group' => $this->groups->rename($groupId, $name), 'message' => 'Group Updated Successfully'];
    }
}
