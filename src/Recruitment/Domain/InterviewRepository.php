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
}
