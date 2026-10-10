<?php

namespace Src\Job\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

/** The jobs job seekers saved. A job seeker saves a job once; saving it again changes nothing. */
interface SavedJobRepository
{
    /** Saves the job for the account and returns whether it is new (false when it was already saved). Never fails on a second save. */
    public function save(int $userId, int $jobId, DateTimeImmutable $savedAt): bool;

    public function remove(int $userId, int $jobId): void;

    public function count(int $userId): int;

    /**
     * @return Page<SavedJob> the most recently saved first
     */
    public function ofUser(int $userId, PageRequest $request): Page;

    public function deleteOfUser(int $userId): void;
}
