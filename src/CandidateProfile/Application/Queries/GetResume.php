<?php

namespace Src\CandidateProfile\Application\Queries;

use Src\CandidateProfile\Domain\CandidateRepository;
use Src\CandidateProfile\Domain\ResumeRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** GET /candidate/resumes/{id} (hanya resume milik kandidat). */
class GetResume
{
    public function __construct(
        private CandidateRepository $candidates,
        private ResumeRepository $resumes,
    ) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId, int $resumeId): array
    {
        $resume = $this->resumes->findOwned($this->candidates->ensureIdForUser($userId), $resumeId)
            ?? throw new EntityNotFound('Not Found');

        return [
            'message' => 'Resume Retried Successfully!',
            'data' => [
                'id' => $resume['id'],
                'name' => $resume['name'],
                'file' => $resume['file'],
                'file_size' => $resume['file_size'],
                'file_url' => $resume['file_url'],
            ],
        ];
    }
}
