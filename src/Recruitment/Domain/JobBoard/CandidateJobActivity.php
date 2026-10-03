<?php

namespace Src\Recruitment\Domain\JobBoard;

/**
 * Aktivitas kandidat pada lowongan: lamaran, bookmark, job alert.
 */
interface CandidateJobActivity
{
    public function candidateIdForUser(int $userId): ?int;

    /**
     * @return array{id:int, name:string, role:string, recent_activities_alert:bool}|null
     */
    public function userInfo(int $userId): ?array;

    /** @return Page baris lowongan + `applied_at` */
    public function appliedJobs(int $candidateId, int $perPage, int $page): Page;

    /** @return Page baris daftar lowongan */
    public function bookmarkedJobs(int $candidateId, int $perPage, int $page): Page;

    /** @return bool true bila bookmark ditambahkan, false bila dihapus */
    public function toggleBookmark(int $candidateId, int $jobId): bool;

    /**
     * Info lowongan untuk bookmark/lamaran, atau null bila tidak ada.
     *
     * @return array{id:int, title:string, slug:string, apply_on:string, company_id:int|null, company_user_id:int|null, company_user_name:string|null}|null
     */
    public function jobSummary(int $jobId): ?array;

    public function ownsResume(int $candidateId, string $resumeId): bool;

    public function hasApplied(int $candidateId, int $jobId): bool;

    public function apply(int $candidateId, int $jobId, string $resumeId, string $coverLetter): void;

    /**
     * Notifikasi job alert (`RelatedJobNotification`) milik user.
     *
     * @return Page baris: ['job' => array|null]
     */
    public function jobAlerts(int $userId, int $perPage, int $page): Page;
}
