<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

interface InterviewRepository
{
    public function findById(int $interviewId): ?Interview;

    /** The pending or confirmed interview of the application, if any. */
    public function active(int $applicationId, bool $forUpdate = false): ?Interview;

    /** @return list<Interview> newest first */
    public function forApplication(int $applicationId): array;

    /** Saves a new interview; fails when the application already has an active one. */
    public function add(Interview $interview): Interview;

    /** Saves the state of an interview that exists. */
    public function update(Interview $interview): void;

    /**
     * The calendar identity of the interviews of an application: their shared UID and the highest sequence used, or null when it never had an interview.
     *
     * @return array{uid: string, sequence: int}|null
     */
    public function calendarChain(int $applicationId): ?array;

    /** Cancels the active interview of the application and returns it, or null when it had none. */
    public function cancelActive(int $applicationId): ?Interview;

    /**
     * The start and end of the interviewer's pending and confirmed interviews that end after `$from`, earliest first.
     *
     * @return list<array{0: DateTimeImmutable, 1: DateTimeImmutable}>
     */
    public function busyRanges(int $interviewerId, DateTimeImmutable $from, array $exceptApplicationIds = []): array;

    /**
     * Confirmed interviews that start after `$from` and no later than `$until` and were not reminded yet.
     *
     * @return list<Interview>
     */
    public function dueForReminder(DateTimeImmutable $from, DateTimeImmutable $until): array;

    /**
     * Confirmed or completed interviews that ended after `$endedAfter` and no later than `$endedBefore` and whose panel was not reminded of the scorecards yet.
     *
     * @return list<Interview>
     */
    public function dueForScorecardReminder(DateTimeImmutable $endedBefore, DateTimeImmutable $endedAfter): array;

    /** Marks the interview's panel as reminded; false when another run did it first. */
    public function claimScorecardReminder(int $interviewId, DateTimeImmutable $at): bool;

    /**
     * Confirmed or completed interviews that ended after `$endedAfter` and no later than `$endedBefore` and whose panel was not followed up on the scorecards a second time yet.
     *
     * @return list<Interview>
     */
    public function dueForScorecardEscalation(DateTimeImmutable $endedBefore, DateTimeImmutable $endedAfter): array;

    /** Marks the second follow-up of the interview as done; false when another run did it first. */
    public function claimScorecardEscalation(int $interviewId, DateTimeImmutable $at): bool;

    /**
     * Confirmed interviews that start after `$from` and no later than `$until` and whose panel was not told they start soon.
     *
     * @return list<Interview>
     */
    public function dueForStartingReminder(DateTimeImmutable $from, DateTimeImmutable $until): array;

    /** Marks the panel as told that the interview starts soon; false when another run did it first. */
    public function claimStartingReminder(int $interviewId, DateTimeImmutable $at): bool;
}
