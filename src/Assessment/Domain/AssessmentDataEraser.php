<?php

namespace Src\Assessment\Domain;

/**
 * Erases what the tests of a user whose account was anonymised hold of them: the answers, the drawings and their files, the speed-column results, the
 * integrity signals, the psychological scores and the text of the report. The invitations, the attempts' timings, the result totals and the report numbers
 * stay (statistics and the provider's register of signed reports).
 */
interface AssessmentDataEraser
{
    /**
     * The paths of the drawings the user's attempts still hold.
     *
     * @return list<string>
     */
    public function drawingPaths(int $userId): array;

    /** Clears the personal content of every attempt of the user. Running it again changes nothing. */
    public function clearPersonalData(int $userId): void;
}
