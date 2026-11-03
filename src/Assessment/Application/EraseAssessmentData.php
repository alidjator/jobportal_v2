<?php

namespace Src\Assessment\Application;

use Src\Assessment\Application\Archive\ArchiveExaminations;
use Src\Assessment\Domain\AssessmentDataEraser;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;

/**
 * Erases what the tests hold of a user whose account was anonymised, after archiving the raw data of the psychological examinations when the retention switch is on (`ArchiveExaminations`): the drawing, invitation, video and report PDF files first, then the answers and the rest of the personal content in one
 * transaction. A failure to delete a file stops everything before any row is cleared, so a retry still knows which files to delete; running it again after
 * success finds nothing and does nothing.
 */
final readonly class EraseAssessmentData implements ErasureStep
{
    public function __construct(
        private ArchiveExaminations $archive,
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
        // With the retention switch on, the raw data of the psychological examinations is archived first; a failure stops here, before anything is deleted.
        $this->archive->execute($userId);

        foreach ([...$this->eraser->drawingPaths($userId), ...$this->eraser->attachmentPaths($userId), ...$this->eraser->videoPaths($userId), ...$this->eraser->reportPdfPaths($userId)] as $path) {
            $this->files->delete($path);
        }

        $this->transactions->run(fn () => $this->eraser->clearPersonalData($userId));
    }
}
