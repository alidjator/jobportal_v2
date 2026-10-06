<?php

namespace Src\JobSeeker\Domain;

interface ResumeRepository
{
    /**
     * The user's resumes, the newest first.
     *
     * @return list<Resume>
     */
    public function forUser(int $userId): array;

    public function find(int $userId, int $id): ?Resume;

    public function count(int $userId): int;

    /** @return int the id of the new resume */
    public function add(Resume $resume): int;

    public function rename(int $userId, int $id, string $name): void;

    public function delete(int $userId, int $id): void;
}
