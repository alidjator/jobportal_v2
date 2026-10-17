<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

/** The answers of the main interviewer and of the extra panel members of interviews to their invitation. */
interface InterviewRsvps
{
    public const int NOTE_MAX = 500;

    /**
     * @param  list<int>  $interviewIds
     * @return array<int, array<int, RsvpAnswer>> the answers by interview id and then by user id, the main interviewer and the extra members
     */
    public function forInterviews(array $interviewIds): array;

    /** Records the answer of the main interviewer or of an extra member of the interview; the caller has checked that the user sits on the panel. */
    public function answer(int $interviewId, int $userId, Rsvp $rsvp, ?string $note, DateTimeImmutable $at): void;
}
