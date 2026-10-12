<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScorecardEraser;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\TransactionManager;

/** Clears the notes and the revision copies of the scorecards of an anonymised applicant's interviews (they may quote the applicant); scores and recommendations stay. */
final readonly class EraseScorecardData implements ErasureStep
{
    public function __construct(private ScorecardEraser $eraser, private TransactionManager $transactions) {}

    public function name(): string
    {
        return 'interview-scorecards';
    }

    public function execute(int $userId): void
    {
        $this->transactions->run(fn () => $this->eraser->clearNotes($userId));
    }
}
