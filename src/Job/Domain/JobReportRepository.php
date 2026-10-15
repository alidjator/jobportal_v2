<?php

namespace Src\Job\Domain;

use DateTimeImmutable;

/** The reports job seekers made about jobs. Callers lock the job row (`JobRepository::lock`) before they count and then insert. */
interface JobReportRepository
{
    public function hasOpenReport(int $jobId, int $reporterUserId): bool;

    /** @return int the id of the new open report */
    public function add(int $jobId, int $reporterUserId, JobReportReason $reason, ?string $note, DateTimeImmutable $at): int;

    /** How many different accounts have an open report about the job (an erased reporter no longer counts). */
    public function distinctOpenReporters(int $jobId): int;

    /**
     * Closes the open reports of a job with the admin's decision.
     *
     * @return array{closed: int, reporters: list<int>} how many reports were closed, and the accounts that reported (those not erased), each once, so they can be told it was reviewed
     */
    public function closeOpen(int $jobId, JobReportStatus $status, int $adminId, DateTimeImmutable $at): array;

    /** Clears the reporter and the note of every report of an anonymised account; the reports stay. */
    public function eraseReporter(int $userId): void;
}
