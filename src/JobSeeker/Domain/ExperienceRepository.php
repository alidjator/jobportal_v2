<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;

interface ExperienceRepository
{
    /**
     * The user's experiences: current ones first, then the latest end date, then the latest start.
     *
     * @return list<WorkExperience>
     */
    public function forUser(int $userId): array;

    public function find(int $userId, int $id): ?WorkExperience;

    public function count(int $userId): int;

    /** @return int the id of the new experience */
    public function add(WorkExperience $experience, DateTimeImmutable $at): int;

    public function save(WorkExperience $experience, DateTimeImmutable $at): void;

    public function delete(int $userId, int $id): void;
}
