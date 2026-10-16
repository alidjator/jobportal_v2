<?php

namespace Src\Job\Domain;

use DateTimeImmutable;

/** Who works on a job. Every job keeps at least one assignment; the use cases enforce it. */
interface JobAssignmentRepository
{
    /** @return list<int> the account ids assigned to the job, in the order they were assigned */
    public function userIds(int $jobId): array;

    /** @return list<JobAssignee> the assignments of the job with their roles, in the order they were assigned */
    public function assignees(int $jobId): array;

    /** @return list<int> the ids of the jobs the account is assigned to */
    public function jobIdsOf(int $userId): array;

    /** @return list<int> the ids of the jobs of the account where it is assigned as a hiring manager */
    public function hiringManagerJobIdsOf(int $userId): array;

    /** Assigns the account; nothing happens when it already is. */
    public function add(int $jobId, int $userId, ?int $assignedByUserId, DateTimeImmutable $at, JobAssignmentRole $role = JobAssignmentRole::Recruiter): void;

    public function changeRole(int $jobId, int $userId, JobAssignmentRole $role): void;

    public function remove(int $jobId, int $userId): void;
}
