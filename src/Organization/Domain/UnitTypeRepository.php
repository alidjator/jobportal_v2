<?php

namespace Src\Organization\Domain;

use DateTimeImmutable;

interface UnitTypeRepository
{
    public function find(int $id): ?UnitType;

    public function create(UnitType $type): UnitType;

    public function save(UnitType $type): void;

    /** @return list<UnitType> by level order, then code */
    public function ofGroup(int $groupId): array;

    public function codeTaken(int $groupId, string $code): bool;

    /** Whether an active unit has the type now or later. */
    public function inUse(int $id, DateTimeImmutable $today): bool;
}
