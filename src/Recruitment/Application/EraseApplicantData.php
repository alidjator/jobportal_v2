<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicantDataEraser;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;

/**
 * Erases what applications hold of a user whose account was anonymised: the resume copies first, then the personal content of every application in one
 * transaction. A failure to delete a file stops everything before any row is cleared, so a retry still knows which files to delete; running it again after
 * success finds nothing and does nothing.
 */
final readonly class EraseApplicantData implements ErasureStep
{
    public function __construct(
        private ApplicantDataEraser $eraser,
        private FileStorage $files,
        private TransactionManager $transactions,
    ) {}

    public function name(): string
    {
        return 'applicant-data';
    }

    public function execute(int $userId): void
    {
        foreach ($this->eraser->resumePaths($userId) as $path) {
            $this->files->delete($path);
        }

        $this->transactions->run(fn () => $this->eraser->clearPersonalData($userId));
    }
}
