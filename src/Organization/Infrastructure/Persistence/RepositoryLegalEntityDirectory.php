<?php

namespace Src\Organization\Infrastructure\Persistence;

use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Organization\LegalEntityDirectory;

final readonly class RepositoryLegalEntityDirectory implements LegalEntityDirectory
{
    public function __construct(private LegalEntityRepository $entities) {}

    public function groupOf(int $legalEntityId): ?int
    {
        return $this->entities->find($legalEntityId)?->groupId;
    }

    public function namesOfGroup(int $groupId): array
    {
        $names = [];

        foreach ($this->entities->ofGroup($groupId) as $entity) {
            $names[(int) $entity->id] = $entity->legalName;
        }

        return $names;
    }

    public function isActive(int $legalEntityId): bool
    {
        return $this->entities->find($legalEntityId)?->isActive() ?? false;
    }
}
