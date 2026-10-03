<?php

namespace Src\CandidateProfile\Application\Commands;

use Src\CandidateProfile\Domain\CandidateRepository;
use Src\CandidateProfile\Domain\ResumeRepository;
use Src\Shared\Application\Contracts\FileStorage;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** DELETE /candidate/delete-resume/{id} (hanya resume milik kandidat). */
class DeleteResume
{
    public function __construct(
        private CandidateRepository $candidates,
        private ResumeRepository $resumes,
        private FileStorage $storage,
    ) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId, int $resumeId): array
    {
        $resume = $this->resumes->findOwned($this->candidates->ensureIdForUser($userId), $resumeId)
            ?? throw new EntityNotFound('Not Found');

        $this->resumes->delete($resumeId);
        $this->storage->delete($resume['file']);

        return ['message' => 'Resume Deleted Successfully!', 'status' => true];
    }
}
