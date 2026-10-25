<?php

namespace Src\Organization\Infrastructure\Persistence;

use Src\Organization\Domain\GroupRepository;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\Organization\GroupDirectory;

final readonly class RepositoryGroupDirectory implements GroupDirectory
{
    public function __construct(private TenantRegistry $tenants, private LegalEntityRepository $entities, private GroupRepository $groups) {}

    public function groupOfCompany(int $companyId): ?int
    {
        $entityId = $this->tenants->legalEntityOf($companyId);

        return $entityId === null ? null : $this->entities->find($entityId)?->groupId;
    }

    public function nameOfGroup(int $groupId): ?string
    {
        return $this->groups->find($groupId)?->name;
    }
}
