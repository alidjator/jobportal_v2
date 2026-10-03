<?php

namespace Src\CandidateProfile\Application\Commands;

use Illuminate\Http\UploadedFile;
use Src\CandidateProfile\Domain\CandidateRepository;
use Src\CandidateProfile\Domain\ResumeRepository;
use Src\Shared\Application\Contracts\FileStorage;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** POST /candidate/update-resume/{id} (hanya resume milik kandidat). */
class UpdateResume
{
    public function __construct(
        private CandidateRepository $candidates,
        private ResumeRepository $resumes,
        private FileStorage $storage,
    ) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId, int $resumeId, string $name, ?UploadedFile $file): array
    {
        $resume = $this->resumes->findOwned($this->candidates->ensureIdForUser($userId), $resumeId)
            ?? throw new EntityNotFound('Not Found');

        $path = $file ? $this->storage->storeFile($file, 'file/candidates') : null;
        $updated = $this->resumes->update($resumeId, $name, $path);

        if ($path !== null) {
            $this->storage->delete($resume['file']);
        }

        return ['message' => 'Resume Updated Successfully!', 'data' => $updated];
    }
}
