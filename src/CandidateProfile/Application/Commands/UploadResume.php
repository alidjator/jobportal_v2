<?php

namespace Src\CandidateProfile\Application\Commands;

use Illuminate\Http\UploadedFile;
use Src\CandidateProfile\Domain\CandidateRepository;
use Src\CandidateProfile\Domain\ResumeRepository;
use Src\Shared\Application\Contracts\FileStorage;

/** POST /candidate/upload-resume. */
class UploadResume
{
    public function __construct(
        private CandidateRepository $candidates,
        private ResumeRepository $resumes,
        private FileStorage $storage,
    ) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId, string $name, UploadedFile $file): array
    {
        $candidateId = $this->candidates->ensureIdForUser($userId);
        $path = $this->storage->storeFile($file, 'file/candidates');

        return [
            'message' => 'Resume uploaded Successfully!',
            'data' => $this->resumes->create($candidateId, $name, $path),
        ];
    }
}
