<?php

namespace Src\Organization\Domain;

use DateTimeImmutable;

interface GradeRepository
{
    public function find(int $id): ?Grade;

    public function create(Grade $grade): Grade;

    public function save(Grade $grade): void;

    /** @return list<Grade> by rank, then code */
    public function ofGroup(int $groupId): array;

    public function codeTaken(int $groupId, string $code): bool;

    /** Whether a position that is active now or later has the grade in its range. */
    public function inUse(int $id, DateTimeImmutable $today): bool;
}
