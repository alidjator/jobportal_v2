<?php

namespace Src\Recruitment\Domain;

/**
 * Erases the personal content of the applications of a user whose account was anonymised. The application rows, their stage history and the answers that
 * are not free text stay (statistics); what the applicant wrote or supplied goes: the profile snapshot, the cover letter, the withdrawal note, the resume
 * copy and the free-text answers.
 */
interface ApplicantDataEraser
{
    /**
     * The paths of the resume copies the user's applications still hold.
     *
     * @return list<string>
     */
    public function resumePaths(int $userId): array;

    /**
     * The paths of the interview recordings stored for the user's applications: they show the applicant's face and voice.
     *
     * @return list<string>
     */
    public function recordingPaths(int $userId): array;

    /** Clears the personal content of every application of the user. Running it again changes nothing. */
    public function clearPersonalData(int $userId): void;
}
