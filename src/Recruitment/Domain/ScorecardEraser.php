<?php

namespace Src\Recruitment\Domain;

/**
 * Erases what the scorecards of an anonymised applicant may quote of them: the scorecard notes, the answer notes and the revision copies. The scores and the
 * recommendations stay, like star ratings.
 */
interface ScorecardEraser
{
    /** Running it again changes nothing. */
    public function clearNotes(int $userId): void;
}
