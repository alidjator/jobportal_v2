<?php

namespace Src\Recruitment\Domain;

/** The extra members of an interview's panel (the main interviewer is on the interview itself); at most {@see self::EXTRA_MAX}. */
interface InterviewPanel
{
    public const int EXTRA_MAX = 3;

    /**
     * @param  list<int>  $interviewIds
     * @return array<int, list<int>> the user ids by interview id
     */
    public function forInterviews(array $interviewIds): array;

    /** Whether the user is the main interviewer or an extra member of any interview of the application, past or present. */
    public function includes(int $userId, int $applicationId): bool;

    /** @param  list<int>  $userIds */
    public function replace(int $interviewId, array $userIds): void;

    public function copy(int $fromInterviewId, int $toInterviewId): void;
}
