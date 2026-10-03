<?php

namespace Src\CandidateProfile\Application\Queries;

use Src\CandidateProfile\Domain\CandidateRepository;

/** GET /candidate (baris kandidat dibuat otomatis bila belum ada). */
class GetCandidateProfile
{
    public function __construct(private CandidateRepository $candidates) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId): array
    {
        return $this->candidates->profile($this->candidates->ensureIdForUser($userId));
    }
}
