<?php

namespace Src\Recruitment\Domain;

/** The questions an interview keeps: a copy of its kit, edited on its own. */
interface InterviewQuestionRepository
{
    /** @return list<KitQuestion> in order */
    public function forInterview(int $interviewId): array;

    /** @return list<InterviewQuestion> in order, with the ids scorecard answers refer to */
    public function withIds(int $interviewId): array;

    /** @param  list<KitQuestion>  $questions */
    public function replace(int $interviewId, array $questions): void;

    /** Copies the questions of one interview to another (a new time for the same interview). */
    public function copy(int $fromInterviewId, int $toInterviewId): void;

    /** Whether a panel member already started a scorecard: from then on the questions are locked. */
    public function scoringStarted(int $interviewId): bool;
}
