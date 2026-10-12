<?php

namespace Src\Recruitment\Domain\Video;

use DateTimeImmutable;

interface AttendanceRepository
{
    public function joined(int $interviewId, InterviewAttendance $attendance): void;

    /** Marks the open attendance of that video server session as ended; nothing happens when there is none. */
    public function left(int $interviewId, int $sessionId, DateTimeImmutable $at): void;

    /** A join on the voice server: the same, but found again by the channel that left. A channel is recorded once while it is open. */
    public function joinedOnChannel(int $interviewId, InterviewAttendance $attendance, string $channelId): void;

    /** Marks the open attendance of that voice channel as ended; nothing happens when there is none. */
    public function leftOnChannel(string $channelId, DateTimeImmutable $at): void;

    /** @return list<InterviewAttendance> in the order people joined */
    public function forInterview(int $interviewId): array;
}
