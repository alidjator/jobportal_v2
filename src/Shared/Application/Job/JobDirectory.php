<?php

namespace Src\Shared\Application\Job;

/** Lets other contexts ask about a job without reading Job's tables. Provided by Job. */
interface JobDirectory
{
    /**
     * The job, when it belongs to the company and the member may see it: a member who sees every job of the company (`jobs.view_all`) sees any, anyone
     * else only the jobs assigned to them. Null otherwise, whether the job is missing, foreign or not theirs.
     */
    public function visibleToMember(int $jobId, int $companyId, int $userId, bool $seesAllJobs): ?JobSummary;

    /**
     * The ids of the jobs of the company that the account is assigned to.
     *
     * @return list<int>
     */
    public function assignedJobIds(int $companyId, int $userId): array;

    /**
     * The ids of the jobs of the company where the account is assigned as a hiring manager: on those jobs they see applications only from the stage boundary.
     *
     * @return list<int>
     */
    public function hiringManagerJobIds(int $companyId, int $userId): array;

    /**
     * The accounts assigned to the job as hiring managers.
     *
     * @return list<int>
     */
    public function hiringManagerUserIds(int $jobId): array;

    /** The stage of Recruitment from which the hiring managers of the job see applications; null when the job uses the default (the company's first interview stage). */
    public function hiringManagerBoundary(int $jobId): ?int;

    /**
     * The explicit boundaries of the jobs of the company, for the jobs that have one.
     *
     * @return array<int, int> the stage id by job id
     */
    public function hiringManagerBoundaries(int $companyId): array;

    /**
     * The jobs of the company that the account is assigned to, whatever their status, newest first.
     *
     * @return list<AssignedJob>
     */
    public function assignedJobs(int $companyId, int $userId): array;

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
     * Who is told about the job: its assignees, or the active company admins when every assignee is suspended.
     *
     * @return list<int>
     */
    public function notificationRecipients(int $jobId): array;

    /**
     * The titles of these jobs whatever their status (an applicant keeps seeing the job they applied to), keyed by job id; an id without a job is missing.
     *
     * @param  list<int>  $jobIds
     * @return array<int, string>
     */
    public function titles(array $jobIds): array;

    /** The title, level and category of the job whatever its status, with the names of the master items in the language; null when the job does not exist. */
    public function classification(int $jobId, string $language): ?JobClassification;

    /** Whether the job is live but on a report hold (enough job seekers reported it; an admin has not decided yet): it takes no new application. A job that does not exist is not on hold. */
    public function isOnHold(int $jobId): bool;

    /** Whether an admin took the job down: its applications stay readable but are no longer processed. A job that does not exist is not taken down. */
    public function isTakenDown(int $jobId): bool;

    /** The vacancies the job states, or null when it states none or does not exist. */
    public function vacancies(int $jobId): ?int;

    /** The days after a rejection before the same person may apply to the job again, or null when the job says never (or does not exist). */
    public function reapplyAfterDays(int $jobId): ?int;

    /** The status value of the job (`active`, `closed`, ...), or null when it does not exist. */
    public function statusOf(int $jobId): ?string;

    /** Locks the job row until the transaction ends, so two changes to what hangs on the job run one after the other. */
    public function lock(int $jobId): void;

    /**
     * The content of the job as a candidate reads it when they apply, ready to be kept with the application (`docs/design/phase-8-job-copy-in-application.md`): title, description, work
     * mode, locations (province, city, address line), the salary only when the job shows it, skills, benefits and tags, with the names of master items in both languages. It holds nothing
     * about the company, the assignees or the status, and is built for any job, live or not. Null when the job does not exist.
     *
     * @return array<string, mixed>|null
     */
    public function contentSnapshot(int $jobId): ?array;
}
