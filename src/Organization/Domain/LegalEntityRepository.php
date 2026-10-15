<?php

namespace Src\Organization\Domain;

interface LegalEntityRepository
{
    public function find(int $id): ?LegalEntity;

    public function create(LegalEntity $entity): LegalEntity;

    public function save(LegalEntity $entity): void;

    /** @return list<LegalEntity> every entity of the group, by name */
    public function ofGroup(int $groupId): array;

    public function nameTaken(int $groupId, string $name, ?int $exceptId = null): bool;

    /**
     * How many entities each group holds, keyed by group id.
     *
     * @param  list<int>  $groupIds
     * @return array<int, int>
     */
    public function countByGroup(array $groupIds): array;
}
