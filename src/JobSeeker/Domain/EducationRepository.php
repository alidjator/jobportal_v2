<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;

interface EducationRepository
{
    /**
     * The user's education entries: current ones first, then the latest end year, then the latest start.
     *
     * @return list<Education>
     */
    public function forUser(int $userId): array;

    public function find(int $userId, int $id): ?Education;

    public function count(int $userId): int;

    /** @return int the id of the new entry */
    public function add(Education $education, DateTimeImmutable $at): int;

    public function save(Education $education, DateTimeImmutable $at): void;

    public function delete(int $userId, int $id): void;
}
