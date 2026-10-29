<?php

namespace Src\Organization\Domain;

use DateTimeImmutable;

interface UnitRepository
{
    public function find(int $id): ?Unit;

    public function create(Unit $unit): Unit;

    public function save(Unit $unit): void;

    /** @return list<Unit> by sort order, then code */
    public function ofEntity(int $legalEntityId): array;

    /**
     * @param  array<int, int>  $orders  sort order by unit id
     */
    public function saveOrders(array $orders): void;

    public function codeTaken(int $legalEntityId, string $code): bool;

    /** @return list<UnitVersion> by start date */
    public function versionsOf(int $unitId): array;

    /** @return array<int, list<UnitVersion>> the timelines of every unit of the entity, by unit id */
    public function timelinesOfEntity(int $legalEntityId): array;

    /**
     * Stores the whole timeline of the unit in place of the one it had.
     *
     * @param  list<UnitVersion>  $versions
     */
    public function replaceVersions(int $unitId, array $versions): void;

    /** Whether an active unit has it as parent, or an active position as its unit, now or later. */
    public function inUse(int $id, DateTimeImmutable $today): bool;
}
