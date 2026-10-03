<?php

namespace Src\Recruitment\Application\JobManagement\Ports;

/**
 * Lamaran pada lowongan perusahaan. `applied_jobs` dan `application_groups` dibaca/ditulis
 * sebatas kolom penempatan grup; data kandidat dibaca read-only.
 */
interface JobApplicationRepository
{
    /** @return list<array<string, mixed>> application group milik perusahaan beserta lamaran untuk lowongan tsb */
    public function groupsWithApplications(int $companyId, int $jobId): array;

    public function applicationBelongsToCompany(int $companyId, int $applicationId): bool;

    public function groupBelongsToCompany(int $companyId, int $groupId): bool;

    public function moveToGroup(int $applicationId, int $groupId): void;

    /** Path file resume bila resume dipakai melamar ke lowongan perusahaan ini. */
    public function resumeFileForCompany(int $companyId, int $resumeId): ?string;
}
