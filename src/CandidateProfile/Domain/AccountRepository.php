<?php

namespace Src\CandidateProfile\Domain;

interface AccountRepository
{
    public function passwordHash(int $userId): ?string;

    public function changePassword(int $userId, string $hash): void;

    /** Hapus token API lalu akun user. */
    public function delete(int $userId): void;
}
