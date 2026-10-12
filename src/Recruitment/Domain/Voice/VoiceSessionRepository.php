<?php

namespace Src\Recruitment\Domain\Voice;

use DateTimeImmutable;

/** The voice side of an interview: when its bridge was opened and closed, and the browser accounts handed to the team. */
interface VoiceSessionRepository
{
    /** Marks the bridge of the interview as open (the first time only). */
    public function open(int $interviewId, DateTimeImmutable $at): void;

    public function isOpen(int $interviewId): bool;

    public function close(int $interviewId, DateTimeImmutable $at): void;

    public function rememberEndpoint(int $interviewId, int $userId, string $username, DateTimeImmutable $at): void;

    /** @return list<string> the accounts handed out for the interview */
    public function endpoints(int $interviewId): array;

    public function forgetEndpoints(int $interviewId): void;

    /** The user that holds the account, or null for an account that is not (any more) handed out for the interview. */
    public function userOfEndpoint(int $interviewId, string $username): ?int;

    /** @return list<int> interviews whose voice bridge is open and whose interview was cancelled, declined, replaced or ran out of its window */
    public function dueForClosing(DateTimeImmutable $now): array;
}
