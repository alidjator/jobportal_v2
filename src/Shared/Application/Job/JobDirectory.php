<?php

namespace Src\Shared\Application\Job;

/** Lets other contexts ask about a job without reading Job's tables. Provided by Job. */
interface JobDirectory
{
    /**
     * The job, when it belongs to the company and the member may see it: a company admin sees every job of the company, anyone else only the jobs
     * assigned to them. Null otherwise, whether the job is missing, foreign or not theirs.
     */
    public function visibleToMember(int $jobId, int $companyId, int $userId, bool $isCompanyAdmin): ?JobSummary;

    /**
     * The ids of the jobs of the company that the account is assigned to.
     *
     * @return list<int>
     */
    public function assignedJobIds(int $companyId, int $userId): array;

    /** The id of the company of a live public job (as the public job page shows it), or null when there is no such job for the public. */
    public function publicCompanyId(int $jobId): ?int;

    /**
     * The jobs among these that are live for the public, as `publicCompanyId` decides.
     *
     * @param  list<int>  $jobIds
     * @return list<int>
     */
    public function liveJobIds(array $jobIds): array;

    /**
     * The accounts the job is assigned to (a job always has at least one), who are told about its applications.
     *
     * @return list<int>
     */
    public function assigneeUserIds(int $jobId): array;

    /**
     * The titles of these jobs whatever their status (an applicant keeps seeing the job they applied to), keyed by job id; an id without a job is missing.
     *
     * @param  list<int>  $jobIds
     * @return array<int, string>
     */
    public function titles(array $jobIds): array;

    /** Whether an admin took the job down: its applications stay readable but are no longer processed. A job that does not exist is not taken down. */
    public function isTakenDown(int $jobId): bool;

    /** Locks the job row until the transaction ends, so two changes to what hangs on the job run one after the other. */
    public function lock(int $jobId): void;
}
