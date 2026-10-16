<?php

namespace Src\Organization\Domain;

use DateTimeImmutable;

interface PositionRepository
{
    public function find(int $id): ?Position;

    public function create(Position $position): Position;

    public function save(Position $position): void;

    /** @return list<Position> by code */
    public function ofEntity(int $legalEntityId): array;

    public function codeTaken(int $legalEntityId, string $code): bool;

    /** @return list<PositionVersion> by start date */
    public function versionsOf(int $positionId): array;

    /** @return array<int, list<PositionVersion>> the timelines of every position of the entity, by position id */
    public function timelinesOfEntity(int $legalEntityId): array;

    /**
     * Stores the whole timeline of the position in place of the one it had.
     *
     * @param  list<PositionVersion>  $versions
     */
    public function replaceVersions(int $positionId, array $versions): void;

    /** Whether an active position has it as the position it reports to, now or later. */
    public function inUse(int $id, DateTimeImmutable $today): bool;
}
