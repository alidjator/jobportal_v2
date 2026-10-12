<?php

namespace Src\Recruitment\Domain\Voice;

use DateTimeImmutable;

interface CallRepository
{
    public function add(InterviewCall $call): InterviewCall;

    public function update(InterviewCall $call): void;

    /** Removes a call that never reached the voice server (the dial request failed), so it does not use up an attempt. */
    public function delete(int $callId): void;

    public function find(int $callId): ?InterviewCall;

    public function findByChannel(string $channelId, bool $forUpdate = false): ?InterviewCall;

    /** The call of the interview that is dialling, ringing or answered. */
    public function liveOf(int $interviewId): ?InterviewCall;

    /** How many times the team dialled for this interview (a call that failed to leave counts nothing, see {@see self::delete()}). */
    public function countOf(int $interviewId): int;

    /** @return list<InterviewCall> oldest first */
    public function forInterview(int $interviewId): array;

    /** Calls in progress on the whole platform. */
    public function liveCount(): int;

    /** Serialises the checks of the platform-wide call capacity, like the video room capacity. Held until the transaction ends. */
    public function lockCapacity(): void;

    /**
     * The seconds of calls of the company in the period: finished calls by their duration, and a call still answered by `$liveSeconds` (what it may still use at most).
     */
    public function secondsUsed(int $companyId, DateTimeImmutable $from, DateTimeImmutable $until, int $liveSeconds): int;

    /** @return list<InterviewCall> live calls dialled before the time; a call with no news for that long was lost */
    public function liveBefore(DateTimeImmutable $dialedBefore): array;
}
