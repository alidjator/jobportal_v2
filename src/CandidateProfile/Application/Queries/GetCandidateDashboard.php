<?php

namespace Src\CandidateProfile\Application\Queries;

use Src\CandidateProfile\Domain\CandidateRepository;

/** GET /candidate/dashboard. */
class GetCandidateDashboard
{
    public function __construct(private CandidateRepository $candidates) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId): array
    {
        return $this->candidates->dashboard($this->candidates->ensureIdForUser($userId), $userId);
    }
}
