<?php

namespace Src\CandidateProfile\Application\Queries;

use Src\CandidateProfile\Domain\CandidateRepository;
use Src\CandidateProfile\Domain\ResumeRepository;

/** GET /candidate/resumes. */
class ListResumes
{
    public function __construct(
        private CandidateRepository $candidates,
        private ResumeRepository $resumes,
    ) {}

    /** @return list<array<string,mixed>> */
    public function __invoke(int $userId): array
    {
        return $this->resumes->listFor($this->candidates->ensureIdForUser($userId));
    }
}
