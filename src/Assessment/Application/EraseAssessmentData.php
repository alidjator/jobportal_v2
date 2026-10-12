<?php

namespace Src\Assessment\Application;

use Src\Assessment\Domain\AssessmentDataEraser;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;

/**
 * Erases what the tests hold of a user whose account was anonymised: the drawing and video files first, then the answers and the rest of the personal content in one
 * transaction. A failure to delete a file stops everything before any row is cleared, so a retry still knows which files to delete; running it again after
 * success finds nothing and does nothing.
 */
final readonly class EraseAssessmentData implements ErasureStep
{
    public function __construct(
        private AssessmentDataEraser $eraser,
        private FileStorage $files,
        private TransactionManager $transactions,
    ) {}

    public function name(): string
    {
        return 'assessment-data';
    }

    public function execute(int $userId): void
    {
        foreach ([...$this->eraser->drawingPaths($userId), ...$this->eraser->videoPaths($userId)] as $path) {
            $this->files->delete($path);
        }

        $this->transactions->run(fn () => $this->eraser->clearPersonalData($userId));
    }
}
