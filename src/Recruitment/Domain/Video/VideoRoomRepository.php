<?php

namespace Src\Recruitment\Domain\Video;

use DateTimeImmutable;

/** The video room columns of the interviews and the members who hold a join token. The interview row exists; these calls change only its video state. */
interface VideoRoomRepository
{
    public function find(int $interviewId): ?VideoRoom;

    /** The interview whose room is open on the video server under this room number. */
    public function interviewOfRoom(int $roomId): ?int;

    /** Records the room that was created for the interview. */
    public function open(int $interviewId, int $roomId, string $pin, string $secret): void;

    public function markWaiting(int $interviewId, DateTimeImmutable $at): void;

    public function admit(int $interviewId, int $byUserId, DateTimeImmutable $at): void;

    public function close(int $interviewId, DateTimeImmutable $at): void;

    /** Rooms that are created and not closed. */
    public function openRoomCount(): int;

    /** Holds a lock until the transaction ends, so two joins cannot both take the last free room. */
    public function lockCapacity(): void;

    public function rememberToken(int $interviewId, int $userId, DateTimeImmutable $at): void;

    /** @return list<int> */
    public function tokenUserIds(int $interviewId): array;

    public function forgetTokens(int $interviewId): void;

    /**
     * Interviews whose open room should be closed: the interview is no longer confirmed, or its join window has ended.
     *
     * @return list<int> interview ids
     */
    public function dueForClosing(DateTimeImmutable $now): array;
}
