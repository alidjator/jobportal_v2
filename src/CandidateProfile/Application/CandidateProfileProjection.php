<?php

namespace Src\CandidateProfile\Application;

use Src\CandidateProfile\Domain\CandidateRepository;
use Src\Shared\Application\Contracts\UserProfileProjection;

/** Profil kandidat untuk respons login/user-info Identity (bentuk `CandidateResource` lama). */
class CandidateProfileProjection implements UserProfileProjection
{
    public function __construct(private CandidateRepository $candidates) {}

    public function role(): string
    {
        return 'candidate';
    }

    public function project(int $userId): array
    {
        return $this->candidates->profile($this->candidates->ensureIdForUser($userId));
    }
}
