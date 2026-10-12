<?php

namespace Src\Recruitment\Domain\Video;

use DateTimeImmutable;

interface RecordingRepository
{
    public function add(InterviewRecording $recording): InterviewRecording;

    public function find(int $id, bool $forUpdate = false): ?InterviewRecording;

    public function update(InterviewRecording $recording): void;

    /** The recording that is running now, if any. */
    public function active(int $interviewId, bool $forUpdate = false): ?InterviewRecording;

    /** Whether recording ever started for the interview (the consent is then locked). */
    public function exists(int $interviewId): bool;

    /** @return list<InterviewRecording> newest first */
    public function forInterview(int $interviewId): array;

    /** Deletes the row; the caller deletes the file. */
    public function delete(int $id): void;

    /** @return list<InterviewRecording> ready recordings that expired before `$now` */
    public function expired(DateTimeImmutable $now): array;

    /** @return list<InterviewRecording> recordings that have been processing since before `$since` */
    public function stuck(DateTimeImmutable $since): array;
}
