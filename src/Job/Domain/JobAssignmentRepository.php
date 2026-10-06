<?php

namespace Src\Job\Domain;

use DateTimeImmutable;

/** Who works on a job. Every job keeps at least one assignment; the use cases enforce it. */
interface JobAssignmentRepository
{
    /** @return list<int> the account ids assigned to the job, in the order they were assigned */
    public function userIds(int $jobId): array;

    /** @return list<int> the ids of the jobs the account is assigned to */
    public function jobIdsOf(int $userId): array;

    /** Assigns the account; nothing happens when it already is. */
    public function add(int $jobId, int $userId, ?int $assignedByUserId, DateTimeImmutable $at): void;

    public function remove(int $jobId, int $userId): void;
}
