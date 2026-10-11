<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\JobSeekerDataEraser;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;

/**
 * Erases what JobSeeker holds for a user whose account was anonymised: the resume files first, then every row in one transaction. A failure to delete a file
 * stops everything before any row is deleted, so a retry still knows which files to delete; running it again after success finds nothing and does nothing.
 */
final readonly class EraseJobSeekerData implements ErasureStep
{
    public function __construct(
        private ResumeRepository $resumes,
        private JobSeekerDataEraser $eraser,
        private FileStorage $files,
        private TransactionManager $transactions,
    ) {}

    public function name(): string
    {
        return 'job-seeker-data';
    }

    public function execute(int $userId): void
    {
        foreach ($this->resumes->forUser($userId) as $resume) {
            $this->files->delete($resume->path);
        }

        $this->transactions->run(fn () => $this->eraser->deleteRows($userId));
    }
}
