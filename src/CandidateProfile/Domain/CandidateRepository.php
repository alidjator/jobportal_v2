<?php

namespace Src\CandidateProfile\Domain;

interface CandidateRepository
{
    /** Id kandidat milik user; baris `candidates` dibuat otomatis bila belum ada. */
    public function ensureIdForUser(int $userId): int;

    /**
     * Bentuk `CandidateResource` aplikasi lama.
     *
     * @return array<string,mixed>
     */
    public function profile(int $candidateId): array;

    /**
     * @return array{profileComplated: int|null, appliedJobs: int, favoriteJobs: int, notifications: int}
     */
    public function dashboard(int $candidateId, int $userId): array;
}
