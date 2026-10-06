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

    /** The id of the company of a live public job (as the public job page shows it), or null when there is no such job for the public. */
    public function publicCompanyId(int $jobId): ?int;

    /** Locks the job row until the transaction ends, so two changes to what hangs on the job run one after the other. */
    public function lock(int $jobId): void;
}
